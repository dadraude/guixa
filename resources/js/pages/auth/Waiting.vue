<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

defineOptions({
    layout: {
        title: t('Waiting for a household'),
        description: t(
            'The membership commission will assign you a household. Until then you can only edit your details.',
        ),
    },
});
</script>

<template>
    <Head :title="t('Waiting for a household')" />

    <div class="flex flex-col gap-4">
        <Button as-child variant="secondary" class="w-full">
            <Link :href="editProfile()" data-test="edit-profile-link">
                {{ t('Edit your profile') }}
            </Link>
        </Button>
        <Button as-child variant="secondary" class="w-full">
            <Link :href="editSecurity()" data-test="edit-password-link">
                {{ t('Change your password') }}
            </Link>
        </Button>

        <div class="text-center text-sm text-muted-foreground">
            <TextLink :href="logout()" as="button" data-test="logout-button">{{
                t('Log out')
            }}</TextLink>
        </div>
    </div>
</template>
