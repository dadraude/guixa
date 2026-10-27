<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { isPilotThemePage } from '@/lib/pilotTheme';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
const page = usePage();
const pilotTheme = computed(() => isPilotThemePage(page.component));
const managerTheme = computed(() => page.component.startsWith('Manage/'));
const referencePilot = computed(() =>
    ['Manage/Cycles/Index', 'Manage/Producers/Index'].includes(page.component),
);
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :pilot-theme="pilotTheme"
        :manager-theme="managerTheme"
        :reference-pilot="referencePilot"
    >
        <slot />
    </AppLayout>
</template>
