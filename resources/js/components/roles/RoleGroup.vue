<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import RoleCandidateSearch from '@/components/roles/RoleCandidateSearch.vue';
import RoleHolderRow from '@/components/roles/RoleHolderRow.vue';

const props = defineProps<{
    group: App.Http.Controllers.Manage.RoleGroupRow;
    query: string;
    candidates: App.Http.Controllers.Manage.RoleCandidateRow[] | null;
    processing: boolean;
    error: string | null;
}>();

defineEmits<{
    search: [role: Guixa.Shared.Domain.Role, query: string];
    grant: [role: Guixa.Shared.Domain.Role, accountId: string];
}>();

const roleLabels: Partial<Record<Guixa.Shared.Domain.Role, string>> = {
    manager: 'Manager',
    finance: 'Finance',
    catalogue: 'Catalogue',
    receiver: 'Receiver',
};
const label = computed(() => trans(roleLabels[props.group.role] ?? ''));
</script>

<template>
    <section
        class="flex flex-col gap-4"
        :aria-labelledby="`role-${group.role}`"
    >
        <h2 :id="`role-${group.role}`" class="text-lg font-medium">
            {{ label }}
        </h2>

        <p
            v-if="group.holders.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ trans('Nobody holds this role yet.') }}
        </p>
        <ul v-else class="pilot-list" role="list">
            <RoleHolderRow
                v-for="holder in group.holders"
                :key="holder.accountId"
                :name="holder.name"
                :email="holder.email"
                :status="holder.status"
            />
        </ul>

        <RoleCandidateSearch
            :role="group.role"
            :role-label="label"
            :query="query"
            :candidates="candidates"
            :processing="processing"
            :error="error"
            @search="(value) => $emit('search', group.role, value)"
            @grant="(accountId) => $emit('grant', group.role, accountId)"
        />
    </section>
</template>
