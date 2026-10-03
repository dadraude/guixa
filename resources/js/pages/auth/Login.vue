<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import MagicLinkController from '@/actions/App/Http/Controllers/Auth/MagicLinkController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: t('Log in to your account'),
        description: t('We will email you a link and a code to log in'),
    },
});

defineProps<{
    status?: string;
}>();

// The magic link is the default way in; the password is one click away.
const usePassword = ref(false);
</script>

<template>
    <Head :title="t('Log in')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-if="!usePassword"
        v-bind="MagicLinkController.store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t('Email address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="magic-link-button"
            >
                <Spinner v-if="processing" />
                {{ t('Email me a link to log in') }}
            </Button>
        </div>
    </Form>

    <Form
        v-else
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t('Email address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ t('Password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    :placeholder="t('Password')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" />
                    <span>{{ t('Remember me') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ t('Log in') }}
            </Button>
        </div>
    </Form>

    <div class="mt-6 text-center text-sm text-muted-foreground">
        <button
            type="button"
            class="text-foreground underline decoration-neutral-300 underline-offset-4 hover:decoration-current dark:decoration-neutral-500"
            data-test="toggle-sign-in-method"
            @click="usePassword = !usePassword"
        >
            {{
                usePassword
                    ? t('Log in with a link by email instead')
                    : t('Log in with my password instead')
            }}
        </button>
    </div>
</template>
