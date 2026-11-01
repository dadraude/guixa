import { I18n, reset } from 'laravel-vue-i18n';
import {
    InvalidDomainError,
    NotSupportedError,
    PasskeyError,
    PasskeyExistsError,
    Passkeys,
    UserCancelledError,
} from '@laravel/passkeys';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import { i18nOptions } from './i18n';
import { passkeyErrorText } from './passkeyErrors';

afterEach(() => {
    reset();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

it('translates installed passkey client error classes and their dynamic hostname', () => {
    new I18n(i18nOptions('ca'));
    I18n.getSharedInstance().loadLanguage('ca');
    expect(passkeyErrorText(new UserCancelledError())).toBe(
        'S’ha cancel·lat l’operació amb la clau d’accés.',
    );
    expect(passkeyErrorText(new PasskeyExistsError())).toBe(
        'Aquest dispositiu ja està registrat com a clau d’accés.',
    );
    expect(passkeyErrorText(new NotSupportedError())).toBe(
        'Aquest navegador no admet claus d’accés.',
    );
    expect(passkeyErrorText(new InvalidDomainError('example.test'))).toContain(
        'example.test',
    );
    expect(passkeyErrorText(new InvalidDomainError('example.test'))).toContain(
        'desenvolupament local',
    );
});

it('uses Catalan fallback for unknown client errors and retains translated server messages', () => {
    new I18n(i18nOptions('ca'));
    I18n.getSharedInstance().loadLanguage('ca');
    expect(
        passkeyErrorText(new PasskeyError('An unknown error occurred.')),
    ).toBe('S’ha produït un error amb la clau d’accés. Torna-ho a provar.');
    expect(
        passkeyErrorText(new PasskeyError('Request failed with status 500')),
    ).toBe('S’ha produït un error amb la clau d’accés. Torna-ho a provar.');
    expect(
        passkeyErrorText(
            new PasskeyError(
                'No s’ha pogut verificar la clau d’accés. Torna-ho a provar.',
            ),
        ),
    ).toBe('No s’ha pogut verificar la clau d’accés. Torna-ho a provar.');
});

it.each([
    new TypeError('Failed to fetch'),
    new Error('Unexpected transport error'),
])(
    'localizes an unexpected error after actual package conversion',
    async (failure) => {
        new I18n(i18nOptions('ca'));
        I18n.getSharedInstance().loadLanguage('ca');
        vi.spyOn(Passkeys, 'isSupported').mockReturnValue(true);
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(failure));

        let converted: PasskeyError | null = null;
        try {
            await Passkeys.verify();
        } catch (error) {
            converted = error as PasskeyError;
        }

        expect(converted).toBeInstanceOf(PasskeyError);
        expect(passkeyErrorText(converted)).toBe(
            'S’ha produït un error amb la clau d’accés. Torna-ho a provar.',
        );
    },
);

it('preserves active Laravel passkey validation detail and summaries after a cold import', () => {
    // This module was imported before the shared translator was installed.
    new I18n(i18nOptions('ca'));
    I18n.getSharedInstance().loadLanguage('ca');

    for (const message of [
        'El camp identificador de la clau d’accés és obligatori.',
        'El camp identificador de la clau d’accés és obligatori. (i 1 error més)',
        'El camp resposta de la clau d’accés és obligatori.',
        'Tipus de credencial és invàlid',
        'Nom no pot ser més gran que 255 caràcters. (i 3 errors més)',
        'El camp recorda la sessió ha de ser verdader o fals',
    ]) {
        expect(passkeyErrorText(new PasskeyError(message))).toBe(message);
    }
    expect(
        passkeyErrorText(new PasskeyError('El camp aliè és obligatori.')),
    ).toBe('S’ha produït un error amb la clau d’accés. Torna-ho a provar.');
});
