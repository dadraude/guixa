<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { cancel } from '@/routes/email-change';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: t('Profile settings'),
                href: edit(),
            },
        ],
    },
});

const props = defineProps<{
    hasMember: boolean;
    phone?: string | null;
    pendingEmail: string | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="t('Profile settings')" />

    <h1 class="sr-only">{{ t('Profile settings') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t('Profile')"
            :description="
                hasMember
                    ? t('Update your name, phone and email address')
                    : t('Update your name and email address')
            "
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('Name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="t('Full name')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div v-if="props.hasMember" class="grid gap-2">
                <Label for="phone">{{ t('Phone (optional)') }}</Label>
                <Input
                    id="phone"
                    type="tel"
                    class="mt-1 block w-full"
                    name="phone"
                    :default-value="props.phone ?? ''"
                    autocomplete="tel"
                    :placeholder="t('Phone (optional)')"
                />
                <InputError class="mt-2" :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t('Email address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :placeholder="t('Email address')"
                />
                <InputError class="mt-2" :message="errors.email" />
                <div v-if="pendingEmail" data-test="pending-email">
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                'We sent a link to :email. Your email will change when you open it.',
                                { email: pendingEmail },
                            )
                        }}
                    </p>
                    <Button
                        type="button"
                        variant="link"
                        class="h-auto p-0"
                        data-test="cancel-email-change-button"
                        @click="router.delete(cancel().url)"
                        >{{ t('Cancel the change') }}</Button
                    >
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                    >{{ t('Save') }}</Button
                >
            </div>
        </Form>
    </div>
</template>
