<?php

declare(strict_types=1);

namespace App\Support;

use Collator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Locale-aware ordering so that "Àlex" sorts before "Zoe"; a plain lowercase
 * compare orders by code point and puts accented letters last.
 *
 * The same ordering is defined twice, for sorting in PHP (compare) and in the
 * database (orderByColumn); keep both here so they cannot drift apart.
 */
final class CatalanCollation
{
    // ICU collation PostgreSQL uses to match the PHP collator's "ca_ES" order.
    private const string SQL_COLLATION = 'ca-x-icu';

    private readonly Collator $collator;

    public function __construct()
    {
        $this->collator = new Collator('ca_ES');
    }

    public function compare(string $a, string $b): int
    {
        return (int) $this->collator->compare($a, $b);
    }

    /**
     * Order a query by a column in Catalan collation. The column is a trusted
     * identifier from code, never user input.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @return Builder<TModel>
     */
    public static function orderByColumn(Builder $query, string $column): Builder
    {
        return $query->orderByRaw($column.' collate "'.self::SQL_COLLATION.'"');
    }
}
