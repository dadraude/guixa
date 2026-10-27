<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { currentItem } = useAppNavigation();
const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ trans('Navigation') }}</SidebarGroupLabel>
        <SidebarMenu class="gap-2">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    class="min-h-11 group-data-[collapsible=icon]:min-h-11 group-data-[collapsible=icon]:min-w-11"
                    :is-active="
                        currentItem &&
                        toUrl(currentItem.href) === toUrl(item.href)
                    "
                    :tooltip="trans(item.title)"
                >
                    <Link
                        :href="item.href"
                        :aria-current="
                            isCurrentUrl(item.href) ? 'page' : undefined
                        "
                    >
                        <component :is="item.icon" aria-hidden="true" />
                        <span>{{ trans(item.title) }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
