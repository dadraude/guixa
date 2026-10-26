<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import { isPilotThemePage } from '@/lib/pilotTheme';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
const page = usePage();
const pilotTheme = computed(() => isPilotThemePage(page.component));
</script>

<template>
    <AuthLayout
        :title="title"
        :description="description"
        :pilot-theme="pilotTheme"
    >
        <slot />
    </AuthLayout>
</template>
