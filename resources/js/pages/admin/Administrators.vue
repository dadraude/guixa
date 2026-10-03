<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AdministratorController from '@/actions/App/Http/Controllers/Admin/AdministratorController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { home } from '@/routes/admin';
import { index } from '@/routes/admin/administrators';

type AdministratorRow = {
    id: string;
    name: string;
    email: string;
    is_owner: boolean;
    is_active: boolean;
};

defineProps<{
    administrators: AdministratorRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('Administration'), href: home() },
            { title: t('Administrators'), href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="t('Administrators')" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4">
        <Heading
            :title="t('Administrators')"
            :description="
                t(
                    'Only the owner adds administrators and deactivates or reactivates them.',
                )
            "
        />

        <ul class="divide-y rounded-lg border" data-test="administrators">
            <li
                v-for="administrator in administrators"
                :key="administrator.id"
                class="flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <div class="flex flex-col">
                    <span class="font-medium">{{ administrator.name }}</span>
                    <span class="text-sm text-muted-foreground">{{
                        administrator.email
                    }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <Badge v-if="administrator.is_owner">{{
                        t('Owner')
                    }}</Badge>
                    <Badge
                        :variant="
                            administrator.is_active ? 'secondary' : 'outline'
                        "
                    >
                        {{
                            administrator.is_active
                                ? t('Active')
                                : t('Inactive')
                        }}
                    </Badge>

                    <template v-if="!administrator.is_owner">
                        <Form
                            v-if="administrator.is_active"
                            v-bind="
                                AdministratorController.deactivate.form(
                                    administrator.id,
                                )
                            "
                        >
                            <Button type="submit" variant="outline" size="sm">{{
                                t('Deactivate')
                            }}</Button>
                        </Form>
                        <Form
                            v-else
                            v-bind="
                                AdministratorController.reactivate.form(
                                    administrator.id,
                                )
                            "
                        >
                            <Button type="submit" variant="outline" size="sm">{{
                                t('Reactivate')
                            }}</Button>
                        </Form>
                    </template>
                </div>
            </li>
        </ul>

        <div class="flex flex-col gap-6">
            <Heading
                variant="small"
                :title="t('Add administrator')"
                :description="
                    t(
                        'They receive an email with a link to log in and will choose a password.',
                    )
                "
            />

            <Form
                v-bind="AdministratorController.store.form()"
                class="space-y-6"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">{{ t('Name') }}</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="off"
                        :placeholder="t('Full name')"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('Email address') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autocomplete="off"
                        :placeholder="t('Email address')"
                    />
                    <InputError :message="errors.email" />
                </div>

                <Button :disabled="processing" data-test="add-administrator">{{
                    t('Add administrator')
                }}</Button>
            </Form>
        </div>
    </div>
</template>
