<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import SettlementDetails from './SettlementDetails.vue';
import AccountHouseholdController from '@/actions/App/Http/Controllers/Manage/AccountHouseholdController';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import HouseholdMemberController from '@/actions/App/Http/Controllers/Manage/HouseholdMemberController';
import HouseholdWalletController from '@/actions/App/Http/Controllers/Finance/HouseholdWalletController';
import InvitationController from '@/actions/App/Http/Controllers/Manage/InvitationController';
import FormSelect from '@/components/FormSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    household: App.Http.HouseholdRow;
    otherHouseholds: App.Http.HouseholdSummaryRow[];
    deactivationWarning: App.Http.HouseholdSettlementRow;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Households', href: HouseholdController.index() },
        ],
    },
});

const otherHouseholdOptions = computed(() =>
    props.otherHouseholds.map((household) => ({
        value: household.id,
        label: household.name,
    })),
);

const expiry = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString('ca-ES') : '';
</script>

<template>
    <Head :title="household.name" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="household.name"
            :description="
                household.active
                    ? trans('Active household')
                    : trans(
                          'Inactive household: adding a member reactivates it',
                      )
            "
        />

        <Form
            v-bind="HouseholdController.update.form(household.id)"
            class="flex items-end gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="rename">{{ trans('Name') }}</Label>
                <Input
                    id="rename"
                    name="name"
                    :default-value="household.name"
                    required
                />
                <InputError :message="errors.name || errors.domain" />
            </div>
            <Button type="submit" variant="secondary" :disabled="processing">
                {{ trans('Rename') }}
            </Button>
        </Form>

        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-medium">{{ trans('Members') }}</h2>

            <p
                v-if="household.members.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ trans('This household has no members.') }}
            </p>

            <ul v-else class="pilot-list">
                <li
                    v-for="member in props.household.members"
                    :key="member.accountId"
                    class="flex flex-col gap-4"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span>
                            <span class="font-medium">{{ member.name }}</span>
                            <span class="ml-2 text-sm text-muted-foreground">
                                {{ member.email }}
                            </span>
                        </span>
                        <StatusIndicator
                            :label="
                                member.invitationPending
                                    ? trans('Invitation pending')
                                    : trans('Active')
                            "
                            :tone="
                                member.invitationPending ? 'warning' : 'success'
                            "
                        />
                    </div>

                    <div class="pilot-action-row">
                        <Form
                            v-if="otherHouseholds.length > 0"
                            v-bind="
                                AccountHouseholdController.update.form(
                                    member.accountId,
                                )
                            "
                            class="flex items-center gap-2"
                            v-slot="{ errors, processing }"
                        >
                            <FormSelect
                                name="household_id"
                                required
                                :options="otherHouseholdOptions"
                                :default-value="otherHouseholdOptions[0]?.value"
                                class="w-full min-w-0 sm:w-fit sm:min-w-44"
                                :aria-label="
                                    trans('Move :name to', {
                                        name: member.name,
                                    })
                                "
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                            >
                                {{ trans('Move') }}
                            </Button>
                            <InputError :message="errors.domain" />
                        </Form>

                        <Form
                            v-bind="
                                AccountHouseholdController.destroy.form(
                                    member.accountId,
                                )
                            "
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                            >
                                {{ trans('Unlink') }}
                            </Button>
                        </Form>
                    </div>

                    <div
                        v-if="member.invitationPending"
                        class="pilot-action-row"
                    >
                        <p class="text-sm text-muted-foreground">
                            <template v-if="member.invitationExpired">
                                {{
                                    trans('Link expired on :date; resend it', {
                                        date: expiry(
                                            member.invitationExpiresAt,
                                        ),
                                    })
                                }}
                            </template>
                            <template v-else>
                                {{
                                    trans('Link valid until :date', {
                                        date: expiry(
                                            member.invitationExpiresAt,
                                        ),
                                    })
                                }}
                            </template>
                        </p>

                        <Form
                            v-bind="
                                InvitationController.resend.form(
                                    member.accountId,
                                )
                            "
                            v-slot="{ errors, processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                            >
                                {{ trans('Resend invitation') }}
                            </Button>
                            <InputError :message="errors.domain" />
                        </Form>

                        <Form
                            v-bind="
                                InvitationController.changeEmail.form(
                                    member.accountId,
                                )
                            "
                            class="flex items-end gap-2"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-1">
                                <Input
                                    name="email"
                                    type="email"
                                    required
                                    :placeholder="trans('New email')"
                                    class="h-8"
                                />
                                <InputError
                                    :message="errors.email || errors.domain"
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                            >
                                {{ trans('Change email') }}
                            </Button>
                        </Form>
                    </div>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-medium">{{ trans('Add a member') }}</h2>

            <Form
                v-bind="HouseholdMemberController.store.form(household.id)"
                :reset-on-success="['name', 'email']"
                class="flex flex-wrap items-end gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="member-name">{{ trans('Name') }}</Label>
                    <Input id="member-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="member-email">{{ trans('Email') }}</Label>
                    <Input
                        id="member-email"
                        name="email"
                        type="email"
                        required
                    />
                    <InputError :message="errors.email || errors.domain" />
                </div>
                <Button type="submit" :disabled="processing">
                    {{ trans('Send invitation') }}
                </Button>
            </Form>
        </section>

        <section
            v-if="household.active"
            class="grid gap-4"
            aria-labelledby="deactivation-heading"
        >
            <h2 id="deactivation-heading" class="text-lg font-medium">
                {{ trans('Deactivate household') }}
            </h2>
            <p class="text-sm">
                {{
                    trans(
                        'Deactivating unlinks every member. Orders, pickups and balance stay available to settle.',
                    )
                }}
            </p>
            <SettlementDetails :settlement="deactivationWarning" />
            <Form
                v-bind="HouseholdController.deactivate.form(household.id)"
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="destructive"
                    class="min-h-11"
                    :disabled="processing"
                >
                    {{ trans('Deactivate household') }}
                </Button>
            </Form>
        </section>
        <section
            v-else
            class="grid gap-4"
            aria-labelledby="reactivation-heading"
        >
            <h2 id="reactivation-heading" class="text-lg font-medium">
                {{ trans('Reactivate household') }}
            </h2>
            <p class="text-sm">
                {{
                    trans(
                        'Members can be linked to this household again after reactivation.',
                    )
                }}
            </p>
            <Form
                v-bind="HouseholdController.reactivate.form(household.id)"
                v-slot="{ processing }"
            >
                <Button type="submit" class="min-h-11" :disabled="processing">
                    {{ trans('Reactivate household') }}
                </Button>
            </Form>
        </section>

        <Link
            :href="HouseholdWalletController.show(household.id)"
            class="text-sm font-medium underline-offset-4 hover:underline"
        >
            {{ trans('Wallet: balance, top-ups and corrections') }}
        </Link>

        <Link
            :href="HouseholdController.index()"
            class="text-sm text-muted-foreground underline-offset-4 hover:underline"
        >
            {{ trans('Back to households') }}
        </Link>
    </div>
</template>
