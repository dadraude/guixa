import { I18n, i18nVue } from 'laravel-vue-i18n';
import type { App } from 'vue';

const messages = import.meta.glob<Record<string, string>>(
    '../../../lang/*.json',
    {
        eager: true,
        import: 'default',
    },
);

export function i18nOptions(locale = 'ca') {
    return {
        lang: messages[`../../../lang/${locale}.json`] ? locale : 'ca',
        fallbackLang: 'ca',
        fallbackMissingTranslations: true,
        resolve: (language: string) =>
            messages[`../../../lang/${language}.json`] ?? {},
    };
}

export function installTranslations(app: App, locale = 'ca'): void {
    const options = i18nOptions(locale);
    app.use(i18nVue, options);
    // Eager messages are ready before the first render, including setup-time labels.
    I18n.getSharedInstance().loadLanguage(options.lang);
}
