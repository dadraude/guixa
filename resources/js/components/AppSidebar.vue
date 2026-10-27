<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { Sidebar, SidebarContent } from '@/components/ui/sidebar';

const { sections } = useAppNavigation();
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="material-nav-drawer">
        <SidebarContent class="pt-4">
            <NavMain :sections="sections" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
