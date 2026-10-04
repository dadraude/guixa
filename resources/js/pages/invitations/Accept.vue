<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AcceptInvitationController from '@/actions/App/Http/Controllers/Invitations/AcceptInvitationController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

defineOptions({
    layout: {
        title: t('Join your household'),
        description: t('Confirm your details'),
    },
});

defineProps<{
    token: string;
    email: string;
    existingUser: boolean;
    name: string | null;
    passwordRules: string;
}>();
</script>

<template>
    <Head :title="t('Accept invitation')" />

    <Form
        v-bind="AcceptInvitationController.accept.form(token)"
        :reset-on-error="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t('Email address') }}</Label>
                <Input id="email" type="email" :model-value="email" disabled />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="name">{{ t('Full name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    v-focus
                    autocomplete="name"
                    :default-value="name ?? ''"
                />
                <InputError :message="errors.name" />
            </div>

            <p
                v-if="existingUser"
                class="text-sm text-muted-foreground"
                data-test="existing-user-notice"
            >
                {{
                    t(
                        'You already have an account with this email, so your password stays the same.',
                    )
                }}
            </p>

            <div v-if="!existingUser" class="grid gap-2">
                <Label for="password">{{ t('New password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    :placeholder="t('New password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div v-if="!existingUser" class="grid gap-2">
                <Label for="password_confirmation">{{
                    t('Confirm password')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    :placeholder="t('Confirm password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="accept-invitation-button"
            >
                <Spinner v-if="processing" />
                {{ t('Accept invitation') }}
            </Button>
        </div>
    </Form>
</template>
