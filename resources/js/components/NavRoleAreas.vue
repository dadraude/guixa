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
import { useCurrentUrl } from '@/composables/useCurrentUrl';

defineProps<{
    areas: App.Http.RoleArea[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup v-if="areas.length > 1" class="px-2 py-0">
        <SidebarGroupLabel>{{ trans('Areas') }}</SidebarGroupLabel>
        <SidebarMenu class="gap-2">
            <SidebarMenuItem v-for="area in areas" :key="area.role">
                <SidebarMenuButton
                    as-child
                    class="min-h-11 group-data-[collapsible=icon]:min-h-11 group-data-[collapsible=icon]:min-w-11"
                    :is-active="isCurrentUrl(area.href)"
                    :tooltip="trans(area.label)"
                >
                    <Link :href="area.href">
                        <span>{{ trans(area.label) }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
