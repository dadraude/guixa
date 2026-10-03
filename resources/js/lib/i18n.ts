import messages from '../../../lang/ca.json';

const catalan: Record<string, string> = messages;

/**
 * Translate a user-facing string. Keys are the English source text, shared
 * with Laravel's `__()` through `lang/ca.json`; `:name` placeholders are
 * replaced from `replace`.
 */
export function t(
    key: string,
    replace: Record<string, string | number> = {},
): string {
    let text = catalan[key] ?? key;

    for (const [name, value] of Object.entries(replace)) {
        text = text.replaceAll(`:${name}`, String(value));
    }

    return text;
}
