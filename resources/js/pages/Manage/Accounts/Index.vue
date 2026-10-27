<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountDeactivationController from '@/actions/App/Http/Controllers/Manage/AccountDeactivationController';
import AccountController from '@/actions/App/Http/Controllers/Manage/AccountController';
import AccountHouseholdController from '@/actions/App/Http/Controllers/Manage/AccountHouseholdController';
import ManagerRoleController from '@/actions/App/Http/Controllers/Manage/ManagerRoleController';
import ActiveSwitch from '@/components/ActiveSwitch.vue';
import FormSelect from '@/components/FormSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Accounts', href: AccountController.index() }],
    },
});

const props = defineProps<{
    accounts: App.Http.AccountRow[];
    households: App.Http.HouseholdSummaryRow[];
}>();

const householdOptions = computed(() =>
    props.households.map((household) => ({
        value: household.id,
        label: household.name,
    })),
);

const isManager = (account: App.Http.AccountRow) =>
    account.roles.includes('manager');
</script>

<template>
    <Head title="Accounts" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            title="Accounts"
            description="Grant roles, link members to households and manage account status"
        />

        <p
            v-if="accounts.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            No accounts yet.
        </p>

        <ul v-else class="pilot-list">
            <li
                v-for="account in accounts"
                :key="account.accountId"
                class="flex flex-col gap-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-full min-w-0">
                        <h2 class="font-medium">{{ account.name }}</h2>
                        <p class="text-sm break-all text-muted-foreground">
                            {{ account.email }}
                        </p>
                    </div>
                    <div
                        class="flex max-w-full min-w-0 flex-wrap items-center gap-4"
                    >
                        <Badge
                            v-for="role in account.roles"
                            :key="role"
                            variant="outline"
                        >
                            {{ role }}
                        </Badge>
                        <StatusIndicator
                            v-if="account.invitationPending"
                            label="Invitation pending"
                            tone="warning"
                        />
                        <Form
                            v-bind="
                                account.active
                                    ? AccountDeactivationController.store.form(
                                          account.accountId,
                                      )
                                    : AccountDeactivationController.destroy.form(
                                          account.accountId,
                                      )
                            "
                            v-slot="{ errors, processing }"
                            class="pilot-status-switch-form"
                        >
                            <ActiveSwitch
                                :id="account.accountId"
                                :active="account.active"
                                :name="account.name"
                                :processing="processing"
                            />
                            <InputError
                                v-if="errors.domain"
                                :message="errors.domain"
                            />
                        </Form>
                    </div>
                </div>

                <p class="text-sm text-muted-foreground">
                    {{
                        account.householdName
                            ? `Household: ${account.householdName}`
                            : 'No household'
                    }}
                </p>

                <div class="pilot-action-row">
                    <Form
                        v-if="account.active"
                        v-bind="
                            AccountHouseholdController.update.form(
                                account.accountId,
                            )
                        "
                        class="pilot-account-household-form grid w-full min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-2 sm:w-auto sm:grid-cols-[minmax(0,16rem)_auto]"
                        v-slot="{ errors, processing }"
                    >
                        <FormSelect
                            name="household_id"
                            required
                            :options="householdOptions"
                            :default-value="
                                account.householdId ??
                                householdOptions[0]?.value
                            "
                            class="w-full min-w-0"
                            :aria-label="`Household for ${account.name}`"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                        >
                            {{ account.householdId ? 'Move' : 'Link' }}
                        </Button>
                        <InputError
                            v-if="errors.household_id || errors.domain"
                            class="col-span-full"
                            :message="errors.household_id || errors.domain"
                        />
                    </Form>

                    <Form
                        v-if="account.householdId"
                        v-bind="
                            AccountHouseholdController.destroy.form(
                                account.accountId,
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
                            Unlink
                        </Button>
                    </Form>

                    <Form
                        v-if="isManager(account)"
                        v-bind="
                            ManagerRoleController.destroy.form(
                                account.accountId,
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
                            Remove manager role
                        </Button>
                        <InputError :message="errors.domain" />
                    </Form>
                    <Form
                        v-else
                        v-bind="
                            ManagerRoleController.update.form(account.accountId)
                        "
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                        >
                            Make manager
                        </Button>
                    </Form>
                </div>
            </li>
        </ul>
    </div>
</template>
