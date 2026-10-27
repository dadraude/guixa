<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import type { BreadcrumbItem } from '@/types';
import { computed, toRef } from 'vue';
import { providePilotTheme } from '@/composables/usePilotTheme';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    pilotTheme?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    pilotTheme: false,
});
providePilotTheme(toRef(props, 'pilotTheme'));

const page = usePage();
const { areas, currentItem } = useAppNavigation();
const sectionTitle = computed(
    () =>
        currentItem.value?.title ??
        props.breadcrumbs.at(-1)?.title ??
        (page.component.startsWith('settings/') ? 'Settings' : undefined) ??
        areas.value.find((area) => page.url.startsWith(area.href))?.label ??
        'Guixa',
);
</script>

<template>
    <AppShell
        variant="sidebar"
        :class="
            pilotTheme
                ? 'material-app-shell pilot-theme bg-background text-foreground'
                : 'material-app-shell'
        "
    >
        <a class="material-skip-link" href="#main-content">
            {{ trans('Skip to content') }}
        </a>
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader
                :breadcrumbs="breadcrumbs"
                :section-title="sectionTitle"
            />
            <div id="main-content" class="material-app-content" tabindex="-1">
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
