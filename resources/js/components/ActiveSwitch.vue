<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';

const props = defineProps<{
    id: string;
    active: boolean;
    name: string;
    processing?: boolean;
}>();

function submitChange(event: Event) {
    const input = event.target as HTMLInputElement;
    input.checked = props.active;
    input.form?.requestSubmit();
}
</script>

<template>
    <label class="pilot-status-switch">
        <input
            type="checkbox"
            class="daisy-toggle daisy-toggle-primary"
            :checked="active"
            :disabled="processing"
            :aria-label="`${trans(active ? 'Deactivate' : 'Reactivate')}: ${name}`"
            :data-test="`${active ? 'deactivate' : 'reactivate'}-${id}`"
            @change="submitChange"
        />
        <span>{{ trans(active ? 'Active' : 'Deactivated') }}</span>
    </label>
</template>
