<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';
import { toRef } from 'vue';
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
</script>

<template>
    <AppShell
        variant="sidebar"
        :class="pilotTheme ? 'pilot-theme bg-background text-foreground' : ''"
    >
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
