<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { t } from '@/lib/i18n';
import { login } from '@/routes';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        title: t('This email is already in use.'),
        description: t(
            'Someone else took this address before the change was confirmed. Your email has not changed.',
        ),
    },
});

// The page is public: a signed-out visitor is sent to sign in, a signed-in one back to the profile.
defineProps<{
    signedIn: boolean;
}>();
</script>

<template>
    <Head :title="t('This email is already in use.')" />

    <p class="text-center text-sm" data-test="email-change-taken">
        <TextLink v-if="signedIn" :href="edit()">{{
            t('Back to your profile')
        }}</TextLink>
        <TextLink v-else :href="login()">{{ t('Log in') }}</TextLink>
    </p>
</template>
