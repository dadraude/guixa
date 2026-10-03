<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SetPasswordController from '@/actions/App/Http/Controllers/Auth/SetPasswordController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { logout } from '@/routes';

defineOptions({
    layout: {
        title: t('Set your password'),
        description: t(
            'Choose a password so you can also log in without waiting for an email',
        ),
    },
});

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head :title="t('Set your password')" />

    <Form
        v-bind="SetPasswordController.store.form()"
        :reset-on-error="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="password">{{ t('New password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    v-focus
                    autocomplete="new-password"
                    :placeholder="t('New password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
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
                data-test="set-password-button"
            >
                <Spinner v-if="processing" />
                {{ t('Save') }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            <TextLink :href="logout()" as="button">{{ t('Log out') }}</TextLink>
        </div>
    </Form>
</template>
