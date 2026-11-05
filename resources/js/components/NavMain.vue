<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavigationSection } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    sections: NavigationSection[];
    currentItem?: NavItem;
}>();

const page = usePage();
const { isMobile, setOpen, state } = useSidebar();
const { isCurrentUrl } = useCurrentUrl();

const activeSectionTitle = computed(
    () =>
        props.sections.find((section) =>
            section.items.some((item) => item === props.currentItem),
        )?.title,
);
const openSections = ref(
    new Set(activeSectionTitle.value ? [activeSectionTitle.value] : []),
);

watch([() => page.url, activeSectionTitle], () => {
    if (activeSectionTitle.value) {
        openSections.value = new Set([
            ...openSections.value,
            activeSectionTitle.value,
        ]);
    }
});

function toggleSection(title: string) {
    const next = new Set(openSections.value);

    if (!isMobile.value && state.value === 'collapsed') {
        setOpen(true);
        next.add(title);
    } else if (next.has(title)) {
        next.delete(title);
    } else {
        next.add(title);
    }

    openSections.value = next;
}

function isOpen(title: string): boolean {
    return openSections.value.has(title);
}

function sectionId(title: string): string {
    return `navigation-${title.toLowerCase().replaceAll(' ', '-')}`;
}
</script>

<template>
    <nav :aria-label="trans('Navigation')">
        <SidebarGroup class="px-2 py-0 group-data-[collapsible=icon]:px-0">
            <SidebarMenu
                class="gap-1 group-data-[collapsible=icon]:items-center"
            >
                <SidebarMenuItem
                    v-for="section in sections"
                    :key="section.title"
                    class="min-w-0 group-data-[collapsible=icon]:w-11"
                    data-test="navigation-section"
                    :data-section="section.title"
                    :data-active="activeSectionTitle === section.title"
                >
                    <template v-if="section.items.length === 1">
                        <SidebarMenuButton
                            as-child
                            class="min-h-11 group-data-[collapsible=icon]:min-h-11 group-data-[collapsible=icon]:min-w-11 group-data-[collapsible=icon]:justify-center"
                            :is-active="currentItem === section.items[0]"
                            :tooltip="trans(section.items[0].title)"
                        >
                            <Link
                                :href="section.items[0].href"
                                :aria-current="
                                    isCurrentUrl(section.items[0].href)
                                        ? 'page'
                                        : undefined
                                "
                            >
                                <component
                                    :is="section.icon"
                                    aria-hidden="true"
                                />
                                <span
                                    class="group-data-[collapsible=icon]:sr-only"
                                >
                                    {{ trans(section.items[0].title) }}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </template>
                    <template v-else>
                        <SidebarMenuButton
                            type="button"
                            class="min-h-11 group-data-[collapsible=icon]:min-h-11 group-data-[collapsible=icon]:min-w-11 group-data-[collapsible=icon]:justify-center"
                            data-test="section-trigger"
                            :is-active="activeSectionTitle === section.title"
                            :tooltip="trans(section.title)"
                            :aria-expanded="isOpen(section.title)"
                            :aria-controls="sectionId(section.title)"
                            @click="toggleSection(section.title)"
                        >
                            <component :is="section.icon" aria-hidden="true" />
                            <span class="group-data-[collapsible=icon]:sr-only">
                                {{ trans(section.title) }}
                            </span>
                            <ChevronDown
                                class="ml-auto size-4 transition-transform group-data-[collapsible=icon]:hidden"
                                :class="{
                                    'rotate-180': isOpen(section.title),
                                }"
                                aria-hidden="true"
                            />
                        </SidebarMenuButton>
                        <ul
                            :id="sectionId(section.title)"
                            v-show="isOpen(section.title)"
                            class="material-nav-submenu mt-1 flex min-w-0 flex-col gap-1 group-data-[collapsible=icon]:hidden"
                            :aria-label="trans(section.title)"
                        >
                            <li
                                v-for="item in section.items"
                                :key="item.title"
                                class="min-w-0"
                            >
                                <SidebarMenuButton
                                    as-child
                                    class="min-h-11 pl-4"
                                    data-test="section-link"
                                    :is-active="currentItem === item"
                                >
                                    <Link
                                        :href="item.href"
                                        :aria-current="
                                            isCurrentUrl(item.href)
                                                ? 'page'
                                                : undefined
                                        "
                                    >
                                        <component
                                            :is="item.icon"
                                            v-if="item.icon"
                                            aria-hidden="true"
                                        />
                                        <span>{{ trans(item.title) }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </li>
                        </ul>
                    </template>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </nav>
</template>
