<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountDeactivationController from '@/actions/App/Http/Controllers/Manage/AccountDeactivationController';
import AccountController from '@/actions/App/Http/Controllers/Manage/AccountController';
import AccountHouseholdController from '@/actions/App/Http/Controllers/Manage/AccountHouseholdController';
import ManagerRoleController from '@/actions/App/Http/Controllers/Manage/ManagerRoleController';
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

    <div class="pilot-page pilot-manager-page">
        <Heading
            title="Accounts"
            description="Grant roles, link members to households and deactivate accounts"
        />

        <ul class="divide-y rounded-lg border">
            <li
                v-for="account in accounts"
                :key="account.accountId"
                class="flex flex-col gap-3 p-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span class="font-medium">{{ account.name }}</span>
                        <span class="ml-2 text-sm text-muted-foreground">
                            {{ account.email }}
                        </span>
                    </span>
                    <span class="flex flex-wrap gap-2">
                        <Badge
                            v-for="role in account.roles"
                            :key="role"
                            variant="outline"
                        >
                            {{ role }}
                        </Badge>
                        <StatusIndicator
                            :label="
                                !account.active
                                    ? 'Deactivated'
                                    : account.invitationPending
                                      ? 'Invitation pending'
                                      : 'Active'
                            "
                            :tone="
                                !account.active
                                    ? 'danger'
                                    : account.invitationPending
                                      ? 'warning'
                                      : 'success'
                            "
                        />
                    </span>
                </div>

                <p class="text-sm text-muted-foreground">
                    {{
                        account.householdName
                            ? `Household: ${account.householdName}`
                            : 'No household'
                    }}
                </p>

                <div class="flex flex-wrap items-start gap-3">
                    <Form
                        v-if="account.active"
                        v-bind="
                            AccountHouseholdController.update.form(
                                account.accountId,
                            )
                        "
                        class="flex max-w-full min-w-0 flex-wrap items-center gap-2"
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
                            class="max-w-full min-w-0 flex-1 basis-44"
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

                    <Form
                        v-if="account.active"
                        v-bind="
                            AccountDeactivationController.store.form(
                                account.accountId,
                            )
                        "
                        v-slot="{ errors, processing }"
                    >
                        <Button
                            type="submit"
                            variant="destructive"
                            size="sm"
                            :disabled="processing"
                        >
                            Deactivate
                        </Button>
                        <InputError :message="errors.domain" />
                    </Form>
                    <Form
                        v-else
                        v-bind="
                            AccountDeactivationController.destroy.form(
                                account.accountId,
                            )
                        "
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="secondary"
                            size="sm"
                            :disabled="processing"
                        >
                            Reactivate
                        </Button>
                    </Form>
                </div>
            </li>
        </ul>
    </div>
</template>
