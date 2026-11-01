import { parse } from 'vue/compiler-sfc';
import ts from 'typescript';

export type UntranslatedText = {
    kind: 'text' | 'attribute' | 'script' | 'expression';
    value: string;
    offset: number;
    end: number;
};

type TemplateNode = {
    type: number;
    content?:
        | string
        | {
              content: string;
              loc: { start: { offset: number }; end: { offset: number } };
          };
    loc: { start: { offset: number }; end: { offset: number } };
    children?: TemplateNode[];
    branches?: TemplateNode[];
    exp?: {
        content: string;
        loc: { start: { offset: number }; end: { offset: number } };
    };
    props?: {
        type: number;
        name: string;
        value?: { content: string };
        arg?: { content: string };
        exp?: {
            content: string;
            loc: { start: { offset: number }; end: { offset: number } };
        };
        loc: { start: { offset: number }; end: { offset: number } };
    }[];
};

const visibleAttributes = new Set([
    'placeholder',
    'title',
    'aria-label',
    'aria-description',
    'aria-placeholder',
    'alt',
    'label',
    'description',
    'loading-label',
    'separator',
]);

// These are a brand, a nonverbal close glyph, a syntactic email example, and the ISO currency code.
const invariantText = new Set(['Guixa', '×', 'email@example.com', 'EUR']);

export function untranslatedStaticText(
    source: string,
    filename: string,
): UntranslatedText[] {
    const descriptor = filename.endsWith('.vue')
        ? parse(source, { filename }).descriptor
        : null;
    const findings: UntranslatedText[] = [];
    const template = descriptor?.template;

    if (template) {
        const ast = template.ast as unknown as TemplateNode;
        const walk = (node: TemplateNode): void => {
            if (node.type === 2) {
                const value =
                    typeof node.content === 'string' ? node.content.trim() : '';
                if (isUserText(value)) {
                    findings.push({
                        kind: 'text',
                        value,
                        offset: node.loc.start.offset,
                        end: node.loc.end.offset,
                    });
                }
            }
            if (node.type === 1) {
                for (const prop of node.props ?? []) {
                    const value = prop.value?.content.trim() ?? '';
                    if (
                        prop.type === 6 &&
                        visibleAttributes.has(prop.name) &&
                        isUserText(value)
                    ) {
                        findings.push({
                            kind: 'attribute',
                            value,
                            offset: prop.loc.start.offset,
                            end: prop.loc.end.offset,
                        });
                    }
                    if (
                        prop.type === 7 &&
                        prop.name === 'bind' &&
                        prop.arg &&
                        visibleAttributes.has(prop.arg.content) &&
                        prop.exp
                    ) {
                        findings.push(
                            ...expressionText(
                                prop.exp.content,
                                prop.exp.loc.start.offset,
                                prop.exp.loc.end.offset,
                            ),
                        );
                    }
                }
            }
            if (
                node.type === 5 &&
                typeof node.content === 'object' &&
                node.content
            ) {
                findings.push(
                    ...expressionText(
                        node.content.content,
                        node.content.loc.start.offset,
                        node.content.loc.end.offset,
                    ),
                );
            }
            for (const child of node.children ?? []) walk(child);
            for (const branch of node.branches ?? []) walk(branch);
        };
        walk(ast);
    }

    const script = filename.endsWith('.vue')
        ? (descriptor?.scriptSetup ?? descriptor?.script)
        : { content: source, loc: { start: { offset: 0 } } };
    if (script) {
        const ast = ts.createSourceFile(
            filename,
            script.content,
            ts.ScriptTarget.Latest,
            true,
            ts.ScriptKind.TS,
        );
        const visit = (node: ts.Node): void => {
            if (
                ts.isCallExpression(node) &&
                ts.isIdentifier(node.expression) &&
                node.expression.text === 'trans' &&
                isInsideHoistedMacro(node)
            ) {
                findings.push({
                    kind: 'script',
                    value: node.getText(ast),
                    offset: script.loc.start.offset + node.getStart(ast),
                    end: script.loc.start.offset + node.getEnd(),
                });
            }
            const templateSource = ts.isTemplateExpression(node)
                ? node.getText(ast)
                : '';
            // DOM ids, skeleton pattern ids and sidebar cookie serialization are machine values.
            const machineTemplate =
                /^`(?:navigation-|pattern-|\$\{SIDEBAR_COOKIE_NAME\})/.test(
                    templateSource,
                );
            if (
                ts.isTemplateExpression(node) &&
                !machineTemplate &&
                (node.templateSpans.some((span) =>
                    /[A-Za-zÀ-ÿ]{2,}/.test(span.literal.text),
                ) ||
                    /[A-Za-zÀ-ÿ]{2,}/.test(node.head.text))
            ) {
                findings.push({
                    kind: 'expression',
                    value: node.getText(ast),
                    offset: script.loc.start.offset + node.getStart(ast),
                    end: script.loc.start.offset + node.getEnd(),
                });
            }
            if (
                ts.isStringLiteral(node) ||
                ts.isNoSubstitutionTemplateLiteral(node)
            ) {
                const value = node.text;
                const parent = node.parent;
                const translationCall =
                    ts.isCallExpression(parent) &&
                    parent.arguments[0] === node &&
                    ts.isIdentifier(parent.expression) &&
                    ['trans', 'transChoice', '__'].includes(
                        parent.expression.text,
                    );
                const userFacing =
                    (ts.isPropertyAssignment(parent) &&
                        ts.isIdentifier(parent.name) &&
                        [
                            'title',
                            'description',
                            'label',
                            'message',
                            'placeholder',
                            'ariaLabel',
                            'buttonText',
                        ].includes(parent.name.text)) ||
                    inDisplayCollection(node) ||
                    (ts.isConditionalExpression(parent) &&
                        /\s|[À-ÿ]/.test(value)) ||
                    (ts.isBinaryExpression(parent) &&
                        ![
                            ts.SyntaxKind.EqualsEqualsToken,
                            ts.SyntaxKind.EqualsEqualsEqualsToken,
                            ts.SyntaxKind.ExclamationEqualsToken,
                            ts.SyntaxKind.ExclamationEqualsEqualsToken,
                        ].includes(parent.operatorToken.kind) &&
                        /\s|[À-ÿ]/.test(value)) ||
                    (ts.isCallExpression(parent) &&
                        ts.isPropertyAccessExpression(parent.expression) &&
                        ['push', 'join'].includes(
                            parent.expression.name.text,
                        ) &&
                        /\s|[À-ÿ]/.test(value));
                if (
                    !translationCall &&
                    !isHoistedLayoutKey(node) &&
                    userFacing &&
                    isUserText(value)
                ) {
                    findings.push({
                        kind: 'script',
                        value,
                        offset: script.loc.start.offset + node.getStart(ast),
                        end: script.loc.start.offset + node.getEnd(),
                    });
                }
            }
            ts.forEachChild(node, visit);
        };
        visit(ast);
    }

    return findings;
}

function isInsideHoistedMacro(node: ts.Node): boolean {
    for (
        let parent: ts.Node | undefined = node.parent;
        parent;
        parent = parent.parent
    ) {
        if (
            ts.isCallExpression(parent) &&
            ts.isIdentifier(parent.expression) &&
            ['defineOptions', 'withDefaults'].includes(parent.expression.text)
        )
            return true;
    }
    return false;
}

// Inertia reads hoisted defineOptions before the i18n plugin is installed. The auth
// layout and Breadcrumbs component resolve these exact source-key contracts at render.
function isHoistedLayoutKey(node: ts.Node): boolean {
    const property = node.parent;
    const layout = property?.parent;
    const options = layout?.parent;
    const call = options?.parent;
    const auth =
        !!property &&
        !!layout &&
        !!options &&
        !!call &&
        !!call.parent &&
        ts.isPropertyAssignment(property) &&
        ts.isIdentifier(property.name) &&
        ['title', 'description'].includes(property.name.text) &&
        ts.isObjectLiteralExpression(layout) &&
        ts.isPropertyAssignment(options) &&
        ts.isIdentifier(options.name) &&
        options.name.text === 'layout' &&
        ts.isObjectLiteralExpression(call) &&
        ts.isCallExpression(call.parent) &&
        ts.isIdentifier(call.parent.expression) &&
        call.parent.expression.text === 'defineOptions';
    if (auth) return true;
    if (
        !ts.isPropertyAssignment(node.parent) ||
        !ts.isIdentifier(node.parent.name) ||
        node.parent.name.text !== 'title'
    )
        return false;
    let breadcrumbs = false;
    let macro = false;
    for (
        let parent: ts.Node | undefined = node.parent;
        parent;
        parent = parent.parent
    ) {
        if (
            ts.isPropertyAssignment(parent) &&
            ts.isIdentifier(parent.name) &&
            parent.name.text === 'breadcrumbs'
        )
            breadcrumbs = true;
        if (
            ts.isCallExpression(parent) &&
            ts.isIdentifier(parent.expression) &&
            parent.expression.text === 'defineOptions'
        )
            macro = true;
    }
    return breadcrumbs && macro;
}

function inDisplayCollection(node: ts.Node): boolean {
    for (let parent = node.parent; parent; parent = parent.parent) {
        if (ts.isVariableDeclaration(parent)) {
            return (
                ts.isIdentifier(parent.name) &&
                /^(weekdays|statusLabels|typeLabels)$/.test(parent.name.text)
            );
        }
    }
    return false;
}

function expressionText(
    expression: string,
    offset: number,
    end: number,
): UntranslatedText[] {
    const prefix = 'const displayValue = (';
    const ast = ts.createSourceFile(
        'display.ts',
        `${prefix}${expression});`,
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TS,
    );
    const findings: UntranslatedText[] = [];
    const visit = (node: ts.Node): void => {
        if (
            ts.isTemplateExpression(node) &&
            (/[A-Za-zÀ-ÿ]/.test(node.head.text) ||
                node.templateSpans.some((span) =>
                    /[A-Za-zÀ-ÿ]/.test(span.literal.text),
                ))
        ) {
            findings.push({
                kind: 'expression',
                value: node.getText(ast),
                offset,
                end,
            });
            return;
        }
        if (
            ts.isStringLiteral(node) ||
            ts.isNoSubstitutionTemplateLiteral(node)
        ) {
            const parent = node.parent;
            const translated =
                ts.isCallExpression(parent) &&
                parent.arguments[0] === node &&
                ts.isIdentifier(parent.expression) &&
                ['trans', 'transChoice', '$t'].includes(parent.expression.text);
            const comparison =
                ts.isBinaryExpression(parent) &&
                [
                    ts.SyntaxKind.EqualsEqualsToken,
                    ts.SyntaxKind.EqualsEqualsEqualsToken,
                    ts.SyntaxKind.ExclamationEqualsToken,
                    ts.SyntaxKind.ExclamationEqualsEqualsToken,
                ].includes(parent.operatorToken.kind);
            if (!translated && !comparison && isUserText(node.text)) {
                findings.push({
                    kind: 'expression',
                    value: node.text,
                    offset,
                    end,
                });
            }
        }
        ts.forEachChild(node, visit);
    };
    visit(ast);
    return findings;
}

function isUserText(value: string): boolean {
    return /[A-Za-zÀ-ÿ]/.test(value) && !invariantText.has(value);
}
