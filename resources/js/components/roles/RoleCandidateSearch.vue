<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RoleHolderRow from '@/components/roles/RoleHolderRow.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const SEARCH_DELAY_MS = 300;

const props = defineProps<{
    role: Guixa.Shared.Domain.Role;
    roleLabel: string;
    query: string;
    // Null until this group has been searched: the page shows results for one group at a time.
    candidates: App.Http.Controllers.Manage.RoleCandidateRow[] | null;
    processing: boolean;
    // The reason the last grant for this group was rejected (R13).
    error: string | null;
}>();

const emit = defineEmits<{
    search: [query: string];
    grant: [accountId: string];
}>();

const typed = ref(props.query);
let timer: ReturnType<typeof setTimeout> | undefined;

function onInput(value: string | number) {
    typed.value = String(value);
    clearTimeout(timer);
    timer = setTimeout(
        () => emit('search', typed.value.trim()),
        SEARCH_DELAY_MS,
    );
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="flex flex-col gap-2" :data-test="`candidate-search-${role}`">
        <label :for="`search-${role}`" class="text-sm font-medium">
            {{ trans('Find an account to grant :role', { role: roleLabel }) }}
        </label>
        <Input
            :id="`search-${role}`"
            type="search"
            name="q"
            autocomplete="off"
            :model-value="typed"
            :placeholder="trans('Name or email')"
            @update:model-value="onInput"
        />

        <p
            v-if="candidates === null || query === ''"
            class="text-sm text-muted-foreground"
        >
            {{ trans('Type a name or email to find an account.') }}
        </p>
        <p
            v-else-if="candidates.length === 0"
            class="text-sm text-muted-foreground"
            role="status"
        >
            {{ trans('No account matches “:query”.', { query }) }}
        </p>
        <ul v-else class="pilot-list" role="list">
            <RoleHolderRow
                v-for="candidate in candidates"
                :key="candidate.accountId"
                :name="candidate.name"
                :email="candidate.email"
                :status="candidate.status"
            >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="min-h-11"
                    :disabled="processing"
                    :aria-label="
                        trans('Grant :role to :name', {
                            role: roleLabel,
                            name: candidate.name,
                        })
                    "
                    @click="emit('grant', candidate.accountId)"
                >
                    {{ trans('Grant') }}
                </Button>
            </RoleHolderRow>
        </ul>
        <InputError :message="error ?? undefined" />
    </div>
</template>
