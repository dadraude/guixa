import {
    inject,
    provide,
    readonly,
    ref,
    type InjectionKey,
    type Ref,
} from 'vue';

const pilotThemeKey: InjectionKey<Readonly<Ref<boolean>>> =
    Symbol('pilotTheme');

export function providePilotTheme(enabled: Readonly<Ref<boolean>>): void {
    provide(pilotThemeKey, enabled);
}

export function usePilotTheme(): Readonly<Ref<boolean>> {
    return inject(pilotThemeKey, readonly(ref(false)));
}
