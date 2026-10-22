<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accept } from '@/routes/invitation';

defineOptions({
    layout: {
        title: 'Set your password',
        description: 'Choose a password to finish creating your account',
    },
});

defineProps<{
    token: string;
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Set your password" />

    <Form
        v-bind="accept.form(token)"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <InputError :message="errors.domain" />

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    autofocus
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="accept-invitation-button"
            >
                <Spinner v-if="processing" />
                Set password
            </Button>
        </div>
    </Form>
</template>
