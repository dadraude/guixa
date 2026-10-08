<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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
    household_id: string | null;
    is_active: boolean;
    can_change_email: boolean;
    can_deactivate: boolean;
};

type HouseholdOption = {
    id: string;
    number: number;
    name: string;
};

type CommissionOption = {
    id: string;
    name: string;
};

type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

defineProps<{
    members: MemberRow[];
    pagination: Pagination;
    households: HouseholdOption[];
    commissions: CommissionOption[];
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

        <Form
            v-bind="MemberController.store.form()"
            class="flex flex-col gap-3 rounded-lg border p-4"
            data-test="create-member"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <h2 class="font-medium">{{ t('Create member') }}</h2>
            <div class="flex flex-wrap items-start gap-2">
                <Input
                    name="name"
                    required
                    class="max-w-64"
                    :aria-label="t('Full name')"
                    :placeholder="t('Full name')"
                />
                <Input
                    name="email"
                    type="email"
                    required
                    class="max-w-64"
                    :aria-label="t('Email address')"
                    :placeholder="t('Email address')"
                />
                <Input
                    name="phone"
                    class="max-w-48"
                    :aria-label="t('Phone (optional)')"
                    :placeholder="t('Phone (optional)')"
                />
                <select
                    name="household_id"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                    :aria-label="t('Household')"
                >
                    <option value="">{{ t('No household') }}</option>
                    <option
                        v-for="household in households"
                        :key="household.id"
                        :value="household.id"
                    >
                        #{{ household.number }} {{ household.name }}
                    </option>
                </select>
            </div>
            <fieldset
                v-if="commissions.length > 0"
                class="flex flex-wrap gap-4"
            >
                <legend class="sr-only">{{ t('Commissions') }}</legend>
                <label
                    v-for="commission in commissions"
                    :key="commission.id"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        name="commission_ids[]"
                        :value="commission.id"
                    />
                    {{ commission.name }}
                </label>
            </fieldset>
            <div>
                <Button type="submit" :disabled="processing">{{
                    t('Create member')
                }}</Button>
            </div>
            <InputError :message="errors.name" />
            <InputError :message="errors.email" />
            <InputError :message="errors.phone" />
            <InputError :message="errors.household_id" />
            <InputError :message="errors.commission_ids" />
        </Form>

        <p v-if="pagination.total === 0" class="text-muted-foreground">
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
                            v-if="member.household_id === null"
                            value=""
                            disabled
                        >
                            {{ t('No household') }}
                        </option>
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

        <nav
            v-if="pagination.last_page > 1"
            class="flex items-center justify-between gap-2"
            data-test="members-pagination"
            :aria-label="t('Pagination')"
        >
            <Button
                v-if="pagination.prev_page_url"
                as-child
                variant="outline"
                size="sm"
            >
                <Link :href="pagination.prev_page_url" preserve-scroll>{{
                    t('Previous')
                }}</Link>
            </Button>
            <span v-else />
            <span class="text-sm text-muted-foreground">{{
                t('Page :page of :last', {
                    page: pagination.current_page,
                    last: pagination.last_page,
                })
            }}</span>
            <Button
                v-if="pagination.next_page_url"
                as-child
                variant="outline"
                size="sm"
            >
                <Link :href="pagination.next_page_url" preserve-scroll>{{
                    t('Next')
                }}</Link>
            </Button>
            <span v-else />
        </nav>
    </div>
</template>
