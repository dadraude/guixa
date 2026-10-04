<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { t } from '@/lib/i18n';

defineOptions({
    layout: {
        title: t('This invitation is no longer valid'),
        description: t(
            'It may have expired, been used already or been cancelled.',
        ),
    },
});
</script>

<template>
    <Head :title="t('This invitation is no longer valid')" />

    <p class="text-center text-sm" data-test="invitation-unavailable">
        {{ t('Ask the membership commission for a new one.') }}
    </p>
</template>
