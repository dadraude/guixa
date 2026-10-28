<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="trans('Profile settings')" />

    <h1 class="sr-only">{{ trans('Profile settings') }}</h1>

    <div class="pilot-panel flex flex-col space-y-6 p-4 sm:p-6">
        <Heading
            variant="small"
            :title="trans('Profile')"
            :description="trans('Update your name and email address')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="material-settings-form"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ trans('Name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="trans('Full name')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ trans('Email address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :placeholder="trans('Email address')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ trans('Your email address is unverified.') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                    >
                        {{
                            trans(
                                'Click here to re-send the verification email.',
                            )
                        }}
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="pilot-status mt-2 text-sm font-medium"
                >
                    {{
                        trans(
                            'A new verification link has been sent to your email address.',
                        )
                    }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                    >{{ trans('Save') }}</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
