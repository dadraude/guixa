<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        sectionTitle: string;
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header class="material-app-bar daisy-navbar" data-test="material-app-bar">
        <div class="daisy-navbar-start min-w-0 gap-4">
            <SidebarTrigger class="min-h-11 min-w-11" />
            <div class="min-w-0 leading-tight">
                <span class="material-app-bar-brand">Guixa</span>
                <span
                    class="material-app-bar-title"
                    data-test="app-section-title"
                >
                    {{ trans(sectionTitle) }}
                </span>
            </div>
        </div>
        <div
            v-if="breadcrumbs.length > 0"
            class="daisy-navbar-end hidden min-w-0 md:flex"
        >
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </div>
    </header>
</template>
