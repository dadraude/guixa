<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { toRef } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { providePilotTheme } from '@/composables/usePilotTheme';
import { home } from '@/routes';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        pilotTheme?: boolean;
    }>(),
    { pilotTheme: false },
);
providePilotTheme(toRef(props, 'pilotTheme'));
</script>

<template>
    <div
        class="material-auth reference-style-auth flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-4 sm:p-6 md:p-10"
        :class="pilotTheme ? 'pilot-theme text-foreground' : ''"
    >
        <div class="pilot-auth w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-bold"
                    >
                        <div
                            class="mb-1 flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                        >
                            <AppLogoIcon class="size-6 fill-current" />
                        </div>
                        <span>Guixa</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="pilot-heading text-xl font-medium">
                            {{ title ? trans(title) : '' }}
                        </h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description ? trans(description) : '' }}
                        </p>
                    </div>
                </div>
                <div class="pilot-panel flex flex-col gap-6 p-4 sm:p-6">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
