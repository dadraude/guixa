<script setup lang="ts">
import { BookOpen, FolderGit2 } from '@lucide/vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const { sections } = useAppNavigation();

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="material-nav-drawer">
        <SidebarContent class="pt-4">
            <NavMain :sections="sections" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
