import {
    InvalidDomainError,
    NotSupportedError,
    PasskeyExistsError,
    UserCancelledError,
    type PasskeyError,
} from '@laravel/passkeys';
import { trans } from 'laravel-vue-i18n';

// The installed package converts both JSON response messages and browser errors to
// its base PasskeyError. Preserve only response copy known from active server paths.
const serverMessageKeys = [
    'Invalid credential format.',
    'Passkey registration session expired. Please try again.',
    'Passkey verification session expired. Please try again.',
    'Unable to sign in with this account.',
    'Unable to register this passkey.',
    'Unable to verify passkey. Please try again.',
    'Passkey not recognized. It may have been removed from your account.',
    'Unable to register passkey. Please try again.',
    'Unauthenticated.',
    'The given data was invalid.',
    'Page Expired',
    'Too Many Requests',
] as const;

// Only fields/rules in the installed registration and verification requests.
// The source keys below mirror lang/ca/validation.php; PHP tests check parity.
const validationTemplates = {
    required: 'The :attribute field is required.',
    string: 'The :attribute field must be a string.',
    array: 'The :attribute field must be an array.',
    in: 'The selected :attribute is invalid.',
    boolean: 'The :attribute field must be true or false.',
    max: 'The :attribute field must not be greater than :max characters.',
} as const;
type ValidationRule = keyof typeof validationTemplates;
const validationFields: readonly [string, readonly ValidationRule[]][] = [
    ['name', ['required', 'string', 'max']],
    ['passkey credential', ['required', 'array']],
    ['credential ID', ['required', 'string']],
    ['raw passkey ID', ['required', 'string']],
    ['credential type', ['required', 'string', 'in']],
    ['passkey response', ['required', 'array']],
    ['remember session', ['boolean']],
];

function isKnownValidationMessage(message: string): boolean {
    // A request can report at most one summary for the finite rules above.
    // Compute localized strings at call time: this module loads before i18n setup.
    const summarySuffixes = [''];
    for (let count = 1; count <= 16; count++) {
        const suffix =
            count === 1
                ? trans('(and :count more error)', { count: String(count) })
                : trans('(and :count more errors)', { count: String(count) });
        summarySuffixes.push(` ${suffix}`);
    }

    return validationFields.some(([attributeKey, rules]) =>
        rules.some((rule) => {
            const localized = trans(validationTemplates[rule], {
                attribute: trans(attributeKey),
                max: '255',
            });
            return summarySuffixes.some(
                (suffix) => message === `${localized}${suffix}`,
            );
        }),
    );
}

/** The package returns typed browser failures and server JSON messages through the same ref. */
export function passkeyErrorText(error: PasskeyError | null): string | null {
    if (!error) return null;
    if (error instanceof NotSupportedError)
        return trans('Passkeys are not supported in this browser.');
    if (error instanceof UserCancelledError)
        return trans('The passkey operation was cancelled.');
    if (error instanceof PasskeyExistsError)
        return trans('This device is already registered as a passkey.');
    if (error instanceof InvalidDomainError)
        return trans(
            "Passkeys can't be used on :domain. For local development, use localhost.",
            {
                domain:
                    /^Passkeys can't be used on (.+)\. For local development, use localhost\.$/.exec(
                        error.message,
                    )?.[1] ??
                    globalThis.location?.hostname ??
                    trans('this domain'),
            },
        );
    if (
        isKnownValidationMessage(error.message) ||
        serverMessageKeys.some((key) => {
            const localized = trans(key);
            return localized !== key && error.message === localized;
        })
    )
        return error.message;
    return trans('A passkey error occurred. Please try again.');
}
