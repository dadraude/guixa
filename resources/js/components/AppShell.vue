<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
    class?: string;
    sidebarWidth?: string;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div
        v-if="variant === 'header'"
        class="flex min-h-screen w-full flex-col"
        :class="$props.class"
    >
        <slot />
    </div>
    <SidebarProvider
        v-else
        :default-open="isOpen"
        :sidebar-width="sidebarWidth ?? 'min(16rem, 30vw)'"
        :class="$props.class"
    >
        <slot />
    </SidebarProvider>
</template>
