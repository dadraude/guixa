export function resolveVisualIdentity(
    pageName: string,
    query: URLSearchParams,
    fallbackPageUrl: string,
): {
    role: string;
    pageUrl: string;
    welcomeAuthenticated: boolean;
} {
    const route = query.get('route');
    if (route !== null && (!route.startsWith('/') || route.startsWith('//'))) {
        throw new Error('Invalid visual route');
    }

    return {
        role: query.get('role') ?? 'member',
        pageUrl: route ?? fallbackPageUrl,
        welcomeAuthenticated:
            pageName === 'Welcome' && query.get('authenticated') === '1',
    };
}
