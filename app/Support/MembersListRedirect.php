<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends the user back to the members list page they acted from. The Referer is
 * only a hint: it is used when it points at the members list of this very host,
 * and the redirect is always built from the members.index route, so it can never
 * leave the list.
 */
final class MembersListRedirect
{
    public const string FILTER_NO_HOUSEHOLD = 'no-household';

    /**
     * The only filter value the list understands; anything else means no filter.
     */
    public static function filter(mixed $value): ?string
    {
        return $value === self::FILTER_NO_HOUSEHOLD ? self::FILTER_NO_HOUSEHOLD : null;
    }

    public static function back(Request $request): RedirectResponse
    {
        return to_route('members.index', self::listQuery($request));
    }

    /**
     * The single page-parsing rule of the members list: digits only, leading
     * zeros ignored, at least 1. Anything else is not a page. A number too long
     * to be a real page is capped so the list can still redirect to its last page.
     */
    public static function page(mixed $value): ?int
    {
        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $digits = ltrim($value, '0');

        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 9 ? 999_999_999 : (int) $digits;
    }

    /**
     * Only the parameters the list understands are carried over (the page and the
     * filter): arbitrary keys, numeric or array ones, must never reach the URL generator.
     *
     * @return array{page?: int, filter?: string}
     */
    private static function listQuery(Request $request): array
    {
        $referer = parse_url((string) $request->headers->get('referer'));

        if (! is_array($referer) || ($referer['host'] ?? null) !== $request->getHost()) {
            return [];
        }

        if (rtrim($referer['path'] ?? '', '/') !== rtrim(route('members.index', absolute: false), '/')) {
            return [];
        }

        parse_str($referer['query'] ?? '', $query);

        $page = self::page($query['page'] ?? null);
        $filter = self::filter($query['filter'] ?? null);

        return array_filter(['filter' => $filter, 'page' => $page], fn ($value) => $value !== null);
    }
}
