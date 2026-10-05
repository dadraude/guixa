<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { t } from '@/lib/i18n';
import { login } from '@/routes';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        title: t('This link is no longer valid'),
        description: t('This link has expired or has already been used.'),
    },
});

// The page is public: a signed-out visitor is sent to sign in, a signed-in one back to the profile.
defineProps<{
    signedIn: boolean;
}>();
</script>

<template>
    <Head :title="t('This link is no longer valid')" />

    <p class="text-center text-sm" data-test="email-change-unavailable">
        <TextLink v-if="signedIn" :href="edit()">{{
            t('Back to your profile')
        }}</TextLink>
        <TextLink v-else :href="login()">{{ t('Log in') }}</TextLink>
    </p>
</template>
