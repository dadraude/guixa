<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');

// Radix focuses the first control without scrolling, so keep it visible in a tall dialog.
const scrollFocusIntoView = (event: FocusEvent): void =>
    (event.target as HTMLElement).scrollIntoView({ block: 'nearest' });
</script>

<template>
    <div class="pilot-panel space-y-6 p-4 sm:p-6">
        <Heading
            variant="small"
            :title="trans('Delete account')"
            :description="trans('Delete your account and all of its resources')"
        />
        <div
            class="space-y-4 rounded-lg border border-destructive bg-destructive/10 p-4"
        >
            <div class="relative space-y-0.5 text-destructive">
                <p class="font-medium">{{ trans('Warning') }}</p>
                <p class="text-sm">
                    {{
                        trans(
                            'Please proceed with caution, this cannot be undone.',
                        )
                    }}
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                        >{{ trans('Delete account') }}</Button
                    >
                </DialogTrigger>
                <DialogContent
                    class="pilot-touch-dialog"
                    @focusin="scrollFocusIntoView"
                >
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>{{
                                trans(
                                    'Are you sure you want to delete your account?',
                                )
                            }}</DialogTitle>
                            <DialogDescription>
                                {{
                                    trans(
                                        'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
                                    )
                                }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="pilot-error grid gap-2">
                            <Label for="password" class="sr-only">{{
                                trans('Password')
                            }}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                ref="passwordInput"
                                :placeholder="trans('Password')"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    {{ trans('Cancel') }}
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                {{ trans('Delete account') }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
