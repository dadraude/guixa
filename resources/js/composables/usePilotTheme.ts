import {
    computed,
    inject,
    provide,
    readonly,
    ref,
    type ComputedRef,
    type InjectionKey,
    type Ref,
} from 'vue';
import type { PilotVariant } from '@/lib/pilotTheme';

const pilotThemeKey: InjectionKey<Readonly<Ref<boolean>>> =
    Symbol('pilotTheme');
const pilotVariantKey: InjectionKey<Readonly<Ref<PilotVariant | null>>> =
    Symbol('pilotVariant');

export function providePilotTheme(enabled: Readonly<Ref<boolean>>): void {
    provide(pilotThemeKey, enabled);
}

export function usePilotTheme(): Readonly<Ref<boolean>> {
    return inject(pilotThemeKey, readonly(ref(false)));
}

export function providePilotVariant(
    variant: Readonly<Ref<PilotVariant | null>>,
): void {
    provide(pilotVariantKey, variant);
}

/** The variant's class for portalled content: empty when no variant is active. */
export function usePilotVariantClass(): ComputedRef<string> {
    const variant = inject(pilotVariantKey, readonly(ref(null)));
    return computed(() => variant.value ?? '');
}
