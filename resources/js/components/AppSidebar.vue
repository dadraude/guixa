<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    FolderGit2,
    Home,
    UserCog,
    LayoutGrid,
    Package,
    Undo2,
} from '@lucide/vue';
import { computed } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/Manage/AccountController';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import ReturnItemController from '@/actions/App/Http/Controllers/Manage/ReturnItemController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavRoleAreas from '@/components/NavRoleAreas.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const roleAreas = computed(() => page.props.roleAreas);

const managerNavItems: NavItem[] = [
    {
        title: 'Households',
        href: HouseholdController.index(),
        icon: Home,
    },
    {
        title: 'Accounts',
        href: AccountController.index(),
        icon: UserCog,
    },
    {
        title: 'Stock',
        href: StockProductController.index(),
        icon: Package,
    },
    {
        title: 'Return items',
        href: ReturnItemController.index(),
        icon: Undo2,
    },
];

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(roleAreas.value.some((area) => area.role === 'manager')
        ? managerNavItems
        : []),
]);

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
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavRoleAreas :areas="roleAreas" />
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
