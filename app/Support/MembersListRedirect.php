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
    public static function back(Request $request): RedirectResponse
    {
        return to_route('members.index', self::listQuery($request));
    }

    /**
     * @return array<int|string, mixed>
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

        if (! isset($query['page'])) {
            return $query;
        }

        $page = is_string($query['page']) ? filter_var($query['page'], FILTER_VALIDATE_INT) : false;

        if ($page === false || $page < 1) {
            unset($query['page']);
        }

        return $query;
    }
}
