<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { t } from '@/lib/i18n';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: t('Email address updated.'),
        description: t('You can now sign in with your new email address.'),
    },
});
</script>

<template>
    <Head :title="t('Email address updated.')" />
    <p class="text-center text-sm" data-test="email-change-updated">
        <TextLink :href="login()">{{ t('Log in') }}</TextLink>
    </p>
</template>
