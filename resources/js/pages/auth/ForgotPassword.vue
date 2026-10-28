<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="trans('Forgot password')" />

    <div
        v-if="status"
        class="pilot-status mb-4 text-center text-sm font-medium"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">{{ trans('Email address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    v-focus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ trans('Email password reset link') }}
                </Button>
            </div>
        </Form>

        <div
            class="flex flex-wrap items-center justify-center gap-x-1 text-center text-sm text-muted-foreground"
        >
            <span>{{ trans('Or, return to') }}</span>
            <TextLink :href="login()">{{ trans('log in') }}</TextLink>
        </div>
    </div>
</template>
