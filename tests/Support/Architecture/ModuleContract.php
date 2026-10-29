<?php

namespace Tests\Support\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use ReflectionClass;

/**
 * @phpstan-type Contract array{owns: string, dependencies: list<string>, exports: array<string, list<string>>}
 * @phpstan-type Manifest array{
 *     modules: array<string, Contract>,
 *     tables: array<string, string>,
 *     table_exceptions: array<string, array{tables: list<string>, access: string, reason: string}>,
 *     dynamic_tables: array<string, array<string, array{parameter: string, reason: string}>>,
 *     sql_exceptions: array<string, array<string, string>>
 * }
 */
final class ModuleContract
{
    /** @param Manifest $manifest */
    public function __construct(private readonly array $manifest) {}

    /** @return list<string> */
    public function violations(string $path, string $source): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
        $nodes = (new NodeTraverser(new NameResolver, new ParentConnectingVisitor))->traverse($nodes);
        $finder = new NodeFinder;
        $module = str_starts_with($path, 'src/') ? explode('/', $path)[1] : null;
        $violations = [];

        if ($module !== null && ! isset($this->manifest['modules'][$module])) {
            return ["{$path}: undeclared module {$module}"];
        }

        $references = $finder->findInstanceOf($nodes, Node\Name::class);
        foreach ($finder->findInstanceOf($nodes, Node\Scalar\String_::class) as $literal) {
            if (str_starts_with(ltrim($literal->value, '\\'), 'Guixa\\')) {
                $references[] = new Node\Name(ltrim($literal->value, '\\'), $literal->getAttributes());
            }
        }
        foreach ($references as $name) {
            if (($module !== null || str_starts_with($name->toString(), 'Guixa\\')) && ! in_array($name->toString(), [Model::class, 'Illuminate\\Foundation\\Auth\\User'], true)
                && is_a($name->toString(), Model::class, true)) {
                $model = new ReflectionClass($name->toString());
                $table = $model->getDefaultProperties()['table'] ?? null;
                if (! is_string($table)) {
                    $table = Str::snake(Str::pluralStudly($model->getShortName()));
                }
                $violations = [...$violations, ...$this->tableViolations($path, $module, new Node\Scalar\String_($table), $name, true)];
            }
            $parts = explode('\\', $name->toString());
            if ($module === null || count($parts) < 4 || $parts[0] !== 'Guixa' || $parts[1] === $module) {
                continue;
            }
            $target = $parts[1];
            $symbol = implode('\\', array_slice($parts, 2));
            $callers = $this->manifest['modules'][$target]['exports'][$symbol] ?? [];
            if (! in_array($target, $this->manifest['modules'][$module]['dependencies'], true)) {
                $violations[] = "{$path}:{$name->getStartLine()}: {$module} may not depend on {$target}";
            }
            if (! in_array($module, $callers, true)) {
                $violations[] = "{$path}:{$name->getStartLine()}: {$target}\\{$symbol} is not exported to {$module}";
            }
            if ($target !== 'Shared' && ! str_starts_with($path, "src/{$module}/Infrastructure/")) {
                $violations[] = "{$path}:{$name->getStartLine()}: foreign module references belong in Infrastructure";
            }
        }

        $connections = [];
        foreach ($finder->findInstanceOf($nodes, Node\Expr\Assign::class) as $assignment) {
            if ($assignment->var instanceof Node\Expr\Variable && is_string($assignment->var->name)
                && $assignment->expr instanceof Node\Expr\StaticCall && $this->isDatabase($assignment->expr)
                && $assignment->expr->name instanceof Node\Identifier && strtolower($assignment->expr->name->toString()) === 'connection') {
                $connections[] = $assignment->var->name;
            }
        }
        foreach ($finder->findInstanceOf($nodes, Node\Expr\CallLike::class) as $call) {
            if (! ($call instanceof Node\Expr\StaticCall || $call instanceof Node\Expr\MethodCall || $call instanceof Node\Expr\NullsafeMethodCall) || ! $call->name instanceof Node\Identifier) {
                continue;
            }
            $method = strtolower($call->name->toString());
            if (in_array($method, ['table', 'from', 'join', 'leftjoin', 'rightjoin', 'crossjoin', 'joinwhere', 'leftjoinwhere', 'rightjoinwhere', 'settable'], true)) {
                if ($call instanceof Node\Expr\StaticCall && ! $this->isDatabase($call) && ! ($call->class instanceof Node\Name && is_a($call->class->toString(), Model::class, true))) {
                    continue;
                }
                $argument = $this->argument($call, 0, 'table');
                $violations = [...$violations, ...$this->tableViolations($path, $module, $argument, $call, $this->writes($call))];
            }
            if ($this->isRawSql($call, $connections)) {
                $sql = $this->argument($call, 0, 'query');
                if (! $sql instanceof Node\Scalar\String_ || ! isset($this->manifest['sql_exceptions'][$path][$sql->value])) {
                    $violations[] = "{$path}:{$call->getStartLine()}: raw SQL requires a reviewed declaration";
                }
            }
        }

        foreach ($finder->findInstanceOf($nodes, Node\Stmt\Class_::class) as $class) {
            $mapped = false;
            foreach ($class->getProperties() as $property) {
                foreach ($property->props as $item) {
                    if ($item->name->toString() === 'table') {
                        $mapped = true;
                        $violations = [...$violations, ...$this->tableViolations($path, $module, $item->default, $item, true)];
                    }
                }
            }
            if (! $mapped && $class->name !== null && in_array($class->extends?->toString(), ['Illuminate\\Database\\Eloquent\\Model', 'Illuminate\\Foundation\\Auth\\User'], true)) {
                $table = new Node\Scalar\String_(Str::snake(Str::pluralStudly($class->name->toString())));
                $violations = [...$violations, ...$this->tableViolations($path, $module, $table, $class, true)];
            }
        }

        return array_values(array_unique($violations));
    }

    private function isDatabase(Node\Expr\StaticCall $call): bool
    {
        return $call->class instanceof Node\Name && $call->class->toString() === 'Illuminate\\Support\\Facades\\DB';
    }

    /** @return list<string> */
    private function tableViolations(string $path, ?string $module, ?Node $expression, Node $node, bool $write): array
    {
        $location = "{$path}:{$node->getStartLine()}";
        if (! $expression instanceof Node\Scalar\String_) {
            return $this->dynamicTableViolations($path, $module, $expression, $node, $write);
        }
        $segments = preg_split('/\\s+/', strtolower(trim($expression->value)));
        $table = $segments[0] ?? '';
        $owner = $this->manifest['tables'][$table] ?? null;
        if ($owner === null) {
            return ["{$location}: table {$table} has no declared owner"];
        }
        if ($owner === $module && str_starts_with($path, "src/{$module}/Infrastructure/")) {
            return [];
        }
        $exception = $this->manifest['table_exceptions'][$path] ?? null;
        if ($exception !== null && in_array($table, $exception['tables'], true) && ($exception['access'] === 'write' || (! $write && $this->reads($node)))) {
            return [];
        }

        return ["{$location}: {$table} belongs to {$owner}; access from {$path} is not permitted"];
    }

    private function argument(Node\Expr\CallLike $call, int $position, string $name): ?Node\Expr
    {
        foreach ($call->getRawArgs() as $argument) {
            if ($argument instanceof Node\Arg && $argument->name?->toString() === $name) {
                return $argument->value;
            }
        }
        $argument = $call->getRawArgs()[$position] ?? null;

        return $argument instanceof Node\Arg && ! $argument->unpack && $argument->name === null ? $argument->value : null;
    }

    private function ancestor(Node $node, string $type): ?Node
    {
        while (($node = $node->getAttribute('parent')) instanceof Node) {
            if ($node instanceof $type) {
                return $node;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function dynamicTableViolations(string $path, ?string $module, ?Node $expression, Node $node, bool $write): array
    {
        $failure = ["{$path}:{$node->getStartLine()}: dynamic table access requires a reviewed declaration"];
        $method = $this->ancestor($node, Node\Stmt\ClassMethod::class);
        $class = $this->ancestor($node, Node\Stmt\Class_::class);
        if (! $method instanceof Node\Stmt\ClassMethod || ! $class instanceof Node\Stmt\Class_ || ! $method->isPrivate()) {
            return $failure;
        }
        $name = $method->name->toString();
        $declaration = $this->manifest['dynamic_tables'][$path][$name] ?? null;
        if ($declaration === null || ! $expression instanceof Node\Expr\Variable || $expression->name !== $declaration['parameter']
            || ! $this->unchangedTableParameter($method, $declaration['parameter'])) {
            return $failure;
        }
        $positions = array_keys(array_filter($method->params, fn (Node\Param $param) => $param->var instanceof Node\Expr\Variable && $param->var->name === $declaration['parameter']));
        if (count($positions) !== 1) {
            return $failure;
        }
        $calls = (new NodeFinder)->find($class->stmts, fn (Node $candidate) => ($candidate instanceof Node\Expr\MethodCall || $candidate instanceof Node\Expr\NullsafeMethodCall || $candidate instanceof Node\Expr\StaticCall)
            && $candidate->name instanceof Node\Identifier && strtolower($candidate->name->toString()) === strtolower($name));
        if ($calls === []) {
            return $failure;
        }
        $violations = [];
        foreach ($calls as $call) {
            if (! $call instanceof Node\Expr\CallLike) {
                continue;
            }
            $argument = $this->argument($call, $positions[0], $declaration['parameter']);
            if (! $argument instanceof Node\Scalar\String_) {
                $violations = [...$violations, ...$failure];

                continue;
            }
            $violations = [...$violations, ...$this->tableViolations($path, $module, $argument, $call, $write)];
        }

        return $violations;
    }

    private function unchangedTableParameter(Node\Stmt\ClassMethod $method, string $parameter): bool
    {
        // This exception is for one existing helper, not arbitrary parameter data flow.
        foreach ((new NodeFinder)->findInstanceOf($method, Node\Expr\Variable::class) as $variable) {
            if ($variable->name !== $parameter) {
                continue;
            }
            $parent = $variable->getAttribute('parent');
            if ($parent instanceof Node\Param && ! $parent->byRef) {
                continue;
            }
            if ($parent instanceof Node\Arg && ! $parent->byRef && ! $parent->unpack) {
                $call = $parent->getAttribute('parent');
                if ($call instanceof Node\Expr\StaticCall && $this->isDatabase($call)
                    && $call->name instanceof Node\Identifier && strtolower($call->name->toString()) === 'table'
                    && $this->argument($call, 0, 'table') === $variable) {
                    continue;
                }
                if ($call instanceof Node\Expr\FuncCall && $call->name instanceof Node\Name && $call->name->toString() === 'in_array'
                    && $this->argument($call, 0, 'needle') === $variable) {
                    continue;
                }
            }

            return false;
        }

        return true;
    }

    /** @param list<string> $connections */
    private function isRawSql(Node\Expr\StaticCall|Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $call, array $connections): bool
    {
        if (! $call->name instanceof Node\Identifier) {
            return false;
        }
        $name = strtolower($call->name->toString());
        if (in_array($name, ['exec', 'prepare', 'selectone', 'selectresultsets', 'scalar', 'selectfromwriteconnection', 'statement', 'unprepared', 'affectingstatement', 'getpdo', 'getreadpdo', 'fromraw'], true)) {
            return true;
        }
        if (in_array($name, ['selectraw', 'whereraw', 'orwhereraw', 'havingraw', 'orhavingraw', 'orderbyraw', 'groupbyraw', 'raw'], true)) {
            $expression = $this->argument($call, 0, 'expression');

            return ! $expression instanceof Node\Scalar\String_ || preg_match('/\\b(select|from|join|insert|update|delete|truncate|alter|create|drop)\\b/i', $expression->value) === 1;
        }
        $sql = $this->argument($call, 0, 'query');
        if (in_array($name, ['query', 'exec', 'prepare', 'select', 'selectone', 'statement', 'unprepared', 'insert', 'update', 'delete'], true)
            && $sql instanceof Node\Scalar\String_ && preg_match('/^\\s*(select|with|insert|update|delete|truncate|alter|create|drop)\\b/i', $sql->value) === 1) {
            return true;
        }
        if (! in_array($name, ['select', 'cursor', 'insert', 'update', 'delete'], true)) {
            return false;
        }
        $receiver = $call;
        while ($receiver instanceof Node\Expr\MethodCall || $receiver instanceof Node\Expr\NullsafeMethodCall) {
            if ($receiver !== $call && $receiver->name instanceof Node\Identifier && in_array(strtolower($receiver->name->toString()), ['table', 'from'], true)) {
                return false;
            }
            $receiver = $receiver->var;
        }

        if ($receiver instanceof Node\Expr\Variable && is_string($receiver->name) && in_array($receiver->name, $connections, true)) {
            return true;
        }

        return $receiver instanceof Node\Expr\StaticCall && $this->isDatabase($receiver) && $receiver->name instanceof Node\Identifier
            && strtolower($receiver->name->toString()) !== 'table';
    }

    private function reads(Node $node): bool
    {
        while ((($parent = $node->getAttribute('parent')) instanceof Node\Expr\MethodCall || $parent instanceof Node\Expr\NullsafeMethodCall) && $parent->var === $node && $parent->name instanceof Node\Identifier) {
            if ((new NodeFinder)->findFirst($parent->getRawArgs(), fn (Node $argument) => $argument instanceof Node\Expr\Closure || $argument instanceof Node\Expr\ArrowFunction) !== null) {
                return false;
            }
            $method = strtolower($parent->name->toString());
            if (in_array($method, ['get', 'first', 'firstorfail', 'find', 'findorfail', 'pluck', 'value', 'exists', 'doesntexist', 'count', 'sum', 'avg', 'min', 'max'], true)) {
                return true;
            }
            // Keep the resolver's exception to simple, literal-column query constraints.
            if (! in_array($method, ['where', 'orwhere', 'wherenull', 'wherenotnull', 'orderby'], true)
                || ! $this->argument($parent, 0, 'column') instanceof Node\Scalar\String_) {
                return false;
            }
            $node = $parent;
        }

        return false;
    }

    private function writes(Node $node): bool
    {
        while ($node instanceof Node) {
            if (($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall) && $node->name instanceof Node\Identifier
                && in_array(strtolower($node->name->toString()), ['insert', 'insertorignore', 'insertgetid', 'update', 'updateorinsert', 'upsert', 'delete', 'truncate', 'increment', 'decrement', 'incrementeach', 'decrementeach'], true)) {
                return true;
            }
            $node = $node->getAttribute('parent');
        }

        return false;
    }
}
