<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookUser,
    Home,
    LayoutGrid,
    Mail,
    ShieldCheck,
    Users,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
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
import { t } from '@/lib/i18n';
import { dashboard, home } from '@/routes';
import { home as adminHome } from '@/routes/admin';
import { index as administratorsIndex } from '@/routes/admin/administrators';
import { index as commissionsIndex } from '@/routes/commissions';
import { index as directoryIndex } from '@/routes/directory';
import { index as householdsIndex } from '@/routes/households';
import { index as invitationsIndex } from '@/routes/households/invitations';
import { index as membersIndex } from '@/routes/members';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const isAdministrator = page.props.auth.actingAs === 'administrator';
    // Administrators and members with the membership role manage the same things.
    const managesMembership =
        isAdministrator || page.props.auth.canManageMembership;

    return [
        ...(isAdministrator
            ? [
                  {
                      title: t('Administration'),
                      href: adminHome(),
                      icon: ShieldCheck,
                  },
              ]
            : [
                  {
                      title: t('Dashboard'),
                      href: dashboard(),
                      icon: LayoutGrid,
                  },
              ]),
        ...(managesMembership
            ? [
                  {
                      title: t('Households'),
                      href: householdsIndex(),
                      icon: Home,
                  },
                  {
                      title: t('Members'),
                      href: membersIndex(),
                      icon: Users,
                  },
                  {
                      title: t('Invitations'),
                      href: invitationsIndex(),
                      icon: Mail,
                  },
              ]
            : []),
        {
            title: t('Commissions'),
            href: commissionsIndex(),
            icon: UsersRound,
        },
        {
            title: t('Directory'),
            href: directoryIndex(),
            icon: BookUser,
        },
        ...(isAdministrator && page.props.auth.isOwner
            ? [
                  {
                      title: t('Administrators'),
                      href: administratorsIndex(),
                      icon: Users,
                  },
              ]
            : []),
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
