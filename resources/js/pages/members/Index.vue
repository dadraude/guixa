<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import MemberController from '@/actions/App/Http/Controllers/Members/MemberController';
import MemberHouseholdController from '@/actions/App/Http/Controllers/Members/MemberHouseholdController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { index } from '@/routes/members';

type MemberRow = {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    household_id: string;
    is_active: boolean;
    can_change_email: boolean;
    can_deactivate: boolean;
};

type HouseholdOption = {
    id: string;
    number: number;
    name: string;
};

defineProps<{
    members: MemberRow[];
    households: HouseholdOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Members'), href: index() }],
    },
});
</script>

<template>
    <Head :title="t('Members')" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4">
        <Heading
            :title="t('Members')"
            :description="
                t(
                    'Fix details for people who cannot do it themselves. A new email works for sign-in at once.',
                )
            "
        />

        <p v-if="members.length === 0" class="text-muted-foreground">
            {{ t('No members yet.') }}
        </p>

        <ul v-else class="divide-y rounded-lg border" data-test="members">
            <li
                v-for="member in members"
                :key="member.id"
                class="flex flex-col gap-3 p-4"
            >
                <Form
                    v-bind="MemberController.update.form(member.id)"
                    class="flex flex-wrap items-start gap-2"
                    v-slot="{ errors, processing }"
                >
                    <Input
                        name="name"
                        required
                        class="max-w-64"
                        :aria-label="t('Full name')"
                        :default-value="member.name"
                    />
                    <Input
                        name="email"
                        type="email"
                        required
                        class="max-w-64"
                        :aria-label="t('Email address')"
                        :default-value="member.email"
                        :disabled="!member.can_change_email"
                        :title="
                            member.can_change_email
                                ? undefined
                                : t('You cannot change this email address.')
                        "
                    />
                    <Input
                        name="phone"
                        class="max-w-48"
                        :aria-label="t('Phone (optional)')"
                        :default-value="member.phone ?? ''"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                        >{{ t('Save') }}</Button
                    >
                    <Badge v-if="!member.is_active" variant="outline">{{
                        t('Inactive')
                    }}</Badge>
                    <InputError :message="errors.name" />
                    <InputError :message="errors.email" />
                    <InputError :message="errors.phone" />
                </Form>

                <Form
                    v-bind="MemberHouseholdController.update.form(member.id)"
                    class="flex flex-wrap items-center gap-2"
                    v-slot="{ errors, processing }"
                >
                    <select
                        name="household_id"
                        class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        :aria-label="t('Household')"
                        :value="member.household_id"
                    >
                        <option
                            v-for="household in households"
                            :key="household.id"
                            :value="household.id"
                        >
                            #{{ household.number }} {{ household.name }}
                        </option>
                    </select>
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                        >{{ t('Move') }}</Button
                    >
                    <InputError :message="errors.household_id" />
                </Form>

                <Form
                    v-bind="
                        member.is_active
                            ? MemberController.deactivate.form(member.id)
                            : MemberController.reactivate.form(member.id)
                    "
                    class="flex flex-wrap items-center gap-2"
                    v-slot="{ errors, processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="
                            processing ||
                            (member.is_active && !member.can_deactivate)
                        "
                        :data-test="
                            member.is_active
                                ? 'deactivate-member'
                                : 'reactivate-member'
                        "
                        >{{
                            member.is_active ? t('Deactivate') : t('Reactivate')
                        }}</Button
                    >
                    <InputError :message="errors.member" />
                </Form>
            </li>
        </ul>
    </div>
</template>
