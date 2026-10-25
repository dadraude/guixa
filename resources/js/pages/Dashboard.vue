<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes';
import { trans } from 'laravel-vue-i18n';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="trans('Dashboard')" />

    <div class="pilot-page">
        <Heading :title="trans('Dashboard')" />
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="pilot-panel relative h-24 overflow-hidden md:aspect-video md:h-auto"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="pilot-panel relative h-24 overflow-hidden md:aspect-video md:h-auto"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="pilot-panel relative h-24 overflow-hidden md:aspect-video md:h-auto"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div class="pilot-panel relative min-h-64 flex-1 overflow-hidden">
            <PlaceholderPattern />
        </div>
    </div>
</template>
