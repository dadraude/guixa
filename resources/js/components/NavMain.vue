<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavigationSection } from '@/composables/useAppNavigation';
import { toUrl } from '@/lib/utils';

defineProps<{
    sections: NavigationSection[];
}>();

const { currentItem } = useAppNavigation();
const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <nav :aria-label="trans('Navigation')">
        <template v-for="(section, index) in sections" :key="section.title">
            <SidebarSeparator
                v-if="index > 0"
                class="my-2 group-data-[collapsible=icon]:hidden"
            />
            <SidebarGroup class="px-2 py-0" data-test="navigation-section">
                <SidebarGroupLabel as="h2">
                    {{ trans(section.title) }}
                </SidebarGroupLabel>
                <SidebarMenu class="gap-2" :aria-label="trans(section.title)">
                    <SidebarMenuItem
                        v-for="item in section.items"
                        :key="item.title"
                    >
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
    </nav>
</template>
