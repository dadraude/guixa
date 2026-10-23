<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AccountDeactivationController from '@/actions/App/Http/Controllers/Manage/AccountDeactivationController';
import AccountController from '@/actions/App/Http/Controllers/Manage/AccountController';
import AccountHouseholdController from '@/actions/App/Http/Controllers/Manage/AccountHouseholdController';
import ManagerRoleController from '@/actions/App/Http/Controllers/Manage/ManagerRoleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Accounts', href: AccountController.index() }],
    },
});

defineProps<{
    accounts: App.Http.AccountRow[];
    households: App.Http.HouseholdSummaryRow[];
}>();

const isManager = (account: App.Http.AccountRow) =>
    account.roles.includes('manager');
</script>

<template>
    <Head title="Accounts" />

    <div class="flex flex-col gap-6 p-4">
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
                        <Badge v-if="!account.active" variant="destructive">
                            Deactivated
                        </Badge>
                        <Badge
                            v-else-if="account.invitationPending"
                            variant="secondary"
                        >
                            Invitation pending
                        </Badge>
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
                        class="flex items-center gap-2"
                        v-slot="{ errors, processing }"
                    >
                        <select
                            name="household_id"
                            required
                            class="h-8 rounded-md border bg-background px-2 text-sm"
                            :aria-label="`Household for ${account.name}`"
                        >
                            <option
                                v-for="household in households"
                                :key="household.id"
                                :value="household.id"
                                :selected="household.id === account.householdId"
                            >
                                {{ household.name }}
                            </option>
                        </select>
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
