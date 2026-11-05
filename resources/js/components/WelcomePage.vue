<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    authenticated: boolean;
    actionHref: string;
}>();

const reducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');
const compact = useMediaQuery('(max-width: 639px)');
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

                <figure class="min-w-0" data-visual-panel>
                    <!-- Filmed from the stageshow demo; the poster is its final frame, shown under reduced motion -->
                    <picture v-if="reducedMotion">
                        <source
                            media="(max-width: 639px)"
                            srcset="/images/welcome/order-hero-compact.png"
                        />
                        <img
                            src="/images/welcome/order-hero.png"
                            :alt="
                                trans(
                                    'A member updates their order and the total follows.',
                                )
                            "
                            class="h-auto w-full"
                        />
                    </picture>
                    <video
                        v-else
                        class="h-auto w-full"
                        autoplay
                        loop
                        muted
                        playsinline
                        :aria-label="
                            trans(
                                'A member updates their order and the total follows.',
                            )
                        "
                        :poster="
                            compact
                                ? '/images/welcome/order-hero-compact.png'
                                : '/images/welcome/order-hero.png'
                        "
                        :src="
                            compact
                                ? '/images/welcome/order-hero-compact.webm'
                                : '/images/welcome/order-hero.webm'
                        "
                    />
                </figure>
            </main>
        </div>
    </div>
</template>
