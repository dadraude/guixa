<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Manage/RoleController';
import RoleHolderController from '@/actions/App/Http/Controllers/Manage/RoleHolderController';
import Heading from '@/components/Heading.vue';
import RoleGroup from '@/components/roles/RoleGroup.vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: RoleController.index() }],
    },
});

defineProps<{
    groups: App.Http.Controllers.Manage.RoleGroupRow[];
    // Deferred: the results for the group and query in the address (D8).
    candidates?: App.Http.Controllers.Manage.RoleCandidateRow[];
}>();

// The address keeps the search, so a grant that redirects back reloads the same results.
const params = new URLSearchParams(
    typeof window === 'undefined' ? '' : window.location.search,
);
const searchedRole = ref<Guixa.Shared.Domain.Role | null>(
    params.get('role') as Guixa.Shared.Domain.Role | null,
);
const searchedQuery = ref(params.get('q') ?? '');

function search(role: Guixa.Shared.Domain.Role, query: string) {
    searchedRole.value = role;
    searchedQuery.value = query;

    router.reload({
        only: ['candidates'],
        data: { role, q: query },
        replace: true,
    });
}

const processing = ref(false);
const grantError = ref<{
    role: Guixa.Shared.Domain.Role;
    message: string;
} | null>(null);

function grant(role: Guixa.Shared.Domain.Role, accountId: string) {
    router.post(
        RoleHolderController.store.url(role),
        { account_id: accountId },
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
                grantError.value = null;
            },
            // The page reloads the current holders, so the manager sees the state the rejection is about (R13).
            onError: (errors) => {
                grantError.value = {
                    role,
                    message: errors.domain ?? errors.account_id ?? '',
                };
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="trans('Roles')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Roles')"
            :description="trans('Grant staff roles and see who holds them')"
        />

        <RoleGroup
            v-for="group in groups"
            :key="group.role"
            :group="group"
            :query="searchedRole === group.role ? searchedQuery : ''"
            :candidates="
                searchedRole === group.role ? (candidates ?? null) : null
            "
            :processing="processing"
            :error="grantError?.role === group.role ? grantError.message : null"
            @search="search"
            @grant="grant"
        />
    </div>
</template>
