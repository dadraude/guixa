<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    verificationLinkSent: boolean;
}>();
</script>

<template>
    <Head :title="trans('Email verification')" />

    <div
        v-if="verificationLinkSent"
        class="pilot-status mb-4 text-center text-sm font-medium"
    >
        {{
            trans(
                'A new verification link has been sent to the email address you provided during registration.',
            )
        }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ trans('Resend verification email') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ trans('Log out') }}
        </TextLink>
    </Form>
</template>
