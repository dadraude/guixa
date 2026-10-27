<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="trans('Appearance settings')" />

    <h1 class="sr-only">{{ trans('Appearance settings') }}</h1>

    <div class="pilot-panel space-y-6 p-3 sm:p-6">
        <Heading variant="small" :title="trans('Appearance settings')" />
        <p class="text-sm text-muted-foreground">
            {{ trans('Select the theme from the top-right menu.') }}
        </p>
    </div>
</template>
