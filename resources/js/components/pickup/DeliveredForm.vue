<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    line: App.Http.PickupLineRow;
    processing: boolean;
}>();

const emit = defineEmits<{
    save: [productId: string, quantity: string];
}>();

const quantity = ref(props.line.delivered);

watch(
    () => props.line.delivered,
    (value) => (quantity.value = value),
);
</script>

<template>
    <form
        class="flex items-center gap-2"
        :data-test="`delivered-form-${line.productId}`"
        @submit.prevent="emit('save', line.productId, quantity)"
    >
        <Input
            v-model="quantity"
            inputmode="decimal"
            :aria-label="`Delivered ${line.name}`"
            class="w-28"
        />
        <Button
            type="submit"
            variant="outline"
            size="sm"
            :disabled="processing"
        >
            Save
        </Button>
    </form>
</template>
