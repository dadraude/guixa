<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CommissionController from '@/actions/App/Http/Controllers/Commissions/CommissionController';
import CommissionMemberController from '@/actions/App/Http/Controllers/Commissions/CommissionMemberController';
import CommissionRoleController from '@/actions/App/Http/Controllers/Commissions/CommissionRoleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MyCommissions from '@/components/MyCommissions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { index } from '@/routes/commissions';

type Person = {
    id: string;
    name: string;
};

type RoleOption = {
    value: string;
    label: string;
};

type CommissionRow = {
    id: string;
    name: string;
    members: Person[];
    roles: RoleOption[];
};

defineProps<{
    commissions: CommissionRow[];
    mine: { id: string; name: string; roles: RoleOption[] }[];
    canManage: boolean;
    canAssignRoles: boolean;
    availableRoles: RoleOption[];
    candidates: Person[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Commissions'), href: index() }],
    },
});
</script>

<template>
    <Head :title="t('Commissions')" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4">
        <Heading
            :title="t('Commissions')"
            :description="
                t('Groups of members in charge of an area of the cooperative.')
            "
        />

        <MyCommissions v-if="mine.length > 0" :commissions="mine" />

        <p v-if="commissions.length === 0" class="text-muted-foreground">
            {{ t('No commissions yet.') }}
        </p>

        <ul v-else class="divide-y rounded-lg border" data-test="commissions">
            <li
                v-for="commission in commissions"
                :key="commission.id"
                class="flex flex-col gap-3 p-4"
            >
                <div
                    v-if="canManage"
                    class="flex flex-wrap items-start justify-between gap-3"
                >
                    <Form
                        v-bind="CommissionController.update.form(commission.id)"
                        class="flex items-center gap-2"
                        v-slot="{ errors, processing }"
                    >
                        <Input
                            name="name"
                            required
                            :aria-label="t('Commission name')"
                            :default-value="commission.name"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                            >{{ t('Rename') }}</Button
                        >
                        <InputError :message="errors.name" />
                    </Form>

                    <Form
                        v-bind="
                            CommissionController.destroy.form(commission.id)
                        "
                        v-slot="{ errors, processing }"
                    >
                        <Button
                            type="submit"
                            variant="destructive"
                            size="sm"
                            :disabled="processing"
                            data-test="delete-commission"
                            >{{ t('Delete') }}</Button
                        >
                        <InputError :message="errors.commission" />
                    </Form>
                </div>
                <h2 v-else class="font-medium">{{ commission.name }}</h2>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-muted-foreground">{{ t('Roles') }}:</span>
                    <span
                        v-if="commission.roles.length === 0"
                        class="text-muted-foreground"
                        >{{ t('No roles') }}</span
                    >
                    <span
                        v-for="role in commission.roles"
                        :key="role.value"
                        class="flex items-center gap-1 rounded-full border px-2 py-0.5"
                        data-test="commission-role"
                    >
                        {{ role.label }}
                        <Form
                            v-if="canAssignRoles"
                            v-bind="
                                CommissionRoleController.destroy.form({
                                    commission: commission.id,
                                    role: role.value,
                                })
                            "
                        >
                            <button
                                type="submit"
                                class="text-muted-foreground hover:text-foreground"
                                :aria-label="t('Remove role')"
                            >
                                ×
                            </button>
                        </Form>
                    </span>
                </div>

                <Form
                    v-if="canAssignRoles"
                    v-bind="CommissionRoleController.store.form(commission.id)"
                    class="flex flex-wrap items-start gap-2"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <select
                        name="role"
                        required
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        :aria-label="t('Choose a role')"
                    >
                        <option value="">{{ t('Choose a role') }}</option>
                        <option
                            v-for="role in availableRoles.filter(
                                (option) =>
                                    !commission.roles.some(
                                        (held) => held.value === option.value,
                                    ),
                            )"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </option>
                    </select>
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                        >{{ t('Add role') }}</Button
                    >
                    <InputError :message="errors.role" />
                </Form>

                <p
                    v-if="commission.members.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('No members yet.') }}
                </p>
                <ul v-else class="flex flex-col gap-1">
                    <li
                        v-for="member in commission.members"
                        :key="member.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span>{{ member.name }}</span>
                        <Form
                            v-if="canManage"
                            v-bind="
                                CommissionMemberController.destroy.form({
                                    commission: commission.id,
                                    member: member.id,
                                })
                            "
                        >
                            <Button type="submit" variant="ghost" size="sm">{{
                                t('Remove')
                            }}</Button>
                        </Form>
                    </li>
                </ul>

                <Form
                    v-if="canManage"
                    v-bind="
                        CommissionMemberController.store.form(commission.id)
                    "
                    class="flex flex-wrap items-start gap-2"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <select
                        name="member_id"
                        required
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        :aria-label="t('Choose a member')"
                    >
                        <option value="">{{ t('Choose a member') }}</option>
                        <option
                            v-for="candidate in candidates"
                            :key="candidate.id"
                            :value="candidate.id"
                        >
                            {{ candidate.name }}
                        </option>
                    </select>
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                        >{{ t('Add member') }}</Button
                    >
                    <InputError :message="errors.member_id" />
                </Form>
            </li>
        </ul>

        <div v-if="canManage" class="flex flex-col gap-6">
            <Heading variant="small" :title="t('Add commission')" />

            <Form
                v-bind="CommissionController.store.form()"
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
                        :placeholder="t('Commission name')"
                    />
                    <InputError :message="errors.name" />
                </div>

                <Button :disabled="processing" data-test="add-commission">{{
                    t('Add commission')
                }}</Button>
            </Form>
        </div>
    </div>
</template>
