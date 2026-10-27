<script setup lang="ts">
import { PackageCheck, ShoppingBasket, UsersRound } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    authenticated: boolean;
    actionHref: string;
}>();
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
                class="grid flex-1 items-center gap-10 py-12 sm:py-16 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)] xl:gap-16"
            >
                <section class="min-w-0">
                    <p class="text-sm font-semibold tracking-wide text-primary">
                        {{ trans('For your cooperative') }}
                    </p>
                    <h1
                        class="mt-4 max-w-2xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                    >
                        {{ trans('A clearer way to work together.') }}
                    </h1>
                    <p
                        class="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground"
                    >
                        {{
                            trans(
                                'Plan orders, coordinate pickups and keep households connected in one place.',
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

                <section
                    class="grid min-w-0 gap-5 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-7"
                    data-visual-panel
                    :aria-label="trans('Your work, organized')"
                >
                    <h2 class="text-lg font-semibold">
                        {{ trans('Your work, organized') }}
                    </h2>
                    <ul class="grid min-w-0 gap-3">
                        <li
                            class="flex min-w-0 flex-wrap items-center gap-2 rounded-xl bg-muted p-2"
                        >
                            <ShoppingBasket
                                class="size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                class="min-w-0 flex-[1_1_8rem] font-medium [overflow-wrap:anywhere]"
                                >{{ trans('Orders') }}</span
                            >
                        </li>
                        <li
                            class="flex min-w-0 flex-wrap items-center gap-2 rounded-xl bg-muted p-2"
                        >
                            <PackageCheck
                                class="size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                class="min-w-0 flex-[1_1_8rem] font-medium [overflow-wrap:anywhere]"
                                >{{ trans('Pickups') }}</span
                            >
                        </li>
                        <li
                            class="flex min-w-0 flex-wrap items-center gap-2 rounded-xl bg-muted p-2"
                        >
                            <UsersRound
                                class="size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                class="min-w-0 flex-[1_1_8rem] font-medium [overflow-wrap:anywhere]"
                                >{{ trans('Households') }}</span
                            >
                        </li>
                    </ul>
                </section>
            </main>
        </div>
    </div>
</template>
