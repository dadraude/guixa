<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppearanceMenu from '@/components/AppearanceMenu.vue';
import NavUser from '@/components/NavUser.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';

defineProps<{ sectionTitle: string }>();
</script>

<template>
    <header
        class="material-app-bar flex items-center justify-between"
        data-test="material-app-bar"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="min-h-11 min-w-11" />
            <div class="material-app-brand">
                <span class="material-app-brand-icon" aria-hidden="true">
                    <AppLogoIcon class="size-5" />
                </span>
                <span>Guixa</span>
            </div>
            <span class="material-app-bar-title" data-test="app-section-title">
                {{ trans(sectionTitle) }}
            </span>
        </div>
        <div class="flex min-w-0 items-center justify-end gap-2">
            <AppearanceMenu />
            <div class="material-app-bar-user">
                <NavUser />
            </div>
        </div>
    </header>
</template>
