<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { t } from '@/lib/i18n';
import { home } from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: t('Administration'),
                href: home(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('Administration')" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            :title="t('Administration')"
            :description="
                t(
                    'As administrator you can do everything in the app, outside any household.',
                )
            "
        />
    </div>
</template>
