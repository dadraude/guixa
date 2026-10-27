<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavMain from '@/components/NavMain.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { Sidebar, SidebarContent } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';

withDefaults(defineProps<{ referencePilot?: boolean }>(), {
    referencePilot: false,
});

const { sections, currentItem } = useAppNavigation();
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="material-nav-drawer">
        <Link
            v-if="referencePilot"
            :href="dashboard()"
            class="reference-sidebar-brand"
            aria-label="Guixa"
        >
            <span class="reference-sidebar-brand-mark" aria-hidden="true">
                <AppLogoIcon class="size-5" />
            </span>
            <span class="group-data-[collapsible=icon]:hidden">Guixa</span>
        </Link>
        <SidebarContent class="pt-4">
            <NavMain :sections="sections" :current-item="currentItem" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
