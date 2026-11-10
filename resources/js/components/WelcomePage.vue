<script setup lang="ts">
import { CalendarCheck, Clock, ShoppingBasket } from '@lucide/vue';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    authenticated: boolean;
    actionHref: string;
}>();

const steps = computed(() => [
    {
        icon: ShoppingBasket,
        label: trans('Order'),
        line: trans('Choose what your household needs.'),
    },
    {
        icon: Clock,
        label: trans('Cutoff'),
        line: trans('Orders close and are sent to the producers.'),
    },
    {
        icon: CalendarCheck,
        label: trans('Pickup'),
        line: trans('Collect your order on pickup day.'),
    },
]);
</script>

<template>
    <div
        class="pilot-theme reference-style-welcome min-h-svh bg-background text-foreground"
        data-visual-root
    >
        <div
            class="mx-auto flex min-h-svh w-full max-w-6xl flex-col px-5 py-6 sm:px-8 lg:px-12"
        >
            <header class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-6" aria-hidden="true" />
                </span>
                <span class="text-xl font-bold tracking-tight">Guixa</span>
            </header>

            <main
                class="welcome-enter flex flex-1 flex-col justify-center gap-12 py-10 sm:py-14"
            >
                <section class="min-w-0">
                    <p class="text-sm font-semibold tracking-wide text-primary">
                        {{ trans('For your cooperative') }}
                    </p>
                    <h1
                        class="mt-4 max-w-4xl text-[clamp(min(2.5rem,11vw),7vw,4.75rem)] leading-[1.05] font-extrabold tracking-tighter text-balance break-words"
                    >
                        {{ trans("This week's order,") }}
                        <span class="text-primary">{{
                            trans('in one place.')
                        }}</span>
                    </h1>
                    <p
                        class="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground"
                    >
                        {{
                            trans(
                                'Order, pick up and follow your household wallet.',
                            )
                        }}
                    </p>
                    <Button as-child size="lg" class="mt-8 min-h-11">
                        <a id="welcome-action" :href="actionHref">
                            {{
                                authenticated
                                    ? trans('Open dashboard')
                                    : trans('Log in')
                            }}
                        </a>
                    </Button>
                </section>

                <ol class="grid gap-4 sm:grid-cols-3">
                    <li
                        v-for="step in steps"
                        :key="step.label"
                        class="min-w-0 bg-card p-5"
                        data-visual-panel
                    >
                        <component
                            :is="step.icon"
                            class="size-6 text-primary"
                            aria-hidden="true"
                        />
                        <p class="mt-3 font-bold">{{ step.label }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ step.line }}
                        </p>
                    </li>
                </ol>
            </main>
        </div>
    </div>
</template>
