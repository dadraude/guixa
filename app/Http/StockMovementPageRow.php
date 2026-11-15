<?php

namespace App\Http;

use Guixa\Stock\Application\StockMovementPage;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A page of a stock product's history with the links to its neighbouring pages. */
#[TypeScript]
final readonly class StockMovementPageRow
{
    /** @param list<StockMovementRow> $lines newest first */
    public function __construct(
        public array $lines,
        public int $total,
        public int $page,
        public ?string $prevUrl,
        public ?string $nextUrl,
    ) {}

    public static function from(StockMovementPage $page, int $perPage, int $current, string $path): self
    {
        $paginator = new LengthAwarePaginator($page->lines, $page->total, $perPage, $current, ['path' => $path]);
        // A page past the end links back to the last page, not to the one just before it.
        $previous = $current > 1 ? $paginator->url(min($current - 1, $paginator->lastPage())) : null;

        return new self(
            array_map(StockMovementRow::from(...), $page->lines),
            $page->total,
            $paginator->currentPage(),
            $previous,
            $paginator->nextPageUrl(),
        );
    }
}
