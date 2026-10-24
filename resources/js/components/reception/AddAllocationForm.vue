<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    product: App.Http.ReceptionProductRow;
    households: App.Http.HouseholdOptionRow[];
    processing: boolean;
}>();

const emit = defineEmits<{
    allocate: [householdId: string, quantity: string];
}>();

const household = ref('');
const quantity = ref('');

// Any active household can be given a share, including one that did not order the product (R16).
const candidates = computed(() =>
    props.households.filter(
        (option) =>
            !props.product.allocations.some(
                (allocation) => allocation.householdId === option.id,
            ),
    ),
);

const submit = () => {
    if (household.value === '') {
        return;
    }

    emit('allocate', household.value, quantity.value);
    household.value = '';
    quantity.value = '';
};
</script>

<template>
    <form
        v-if="candidates.length > 0"
        class="flex flex-wrap items-center gap-2 pt-2"
        :data-test="`add-allocation-${product.productId}`"
        @submit.prevent="submit"
    >
        <select
            v-model="household"
            :aria-label="`Household to allocate ${product.name} to`"
            class="h-9 rounded-md border bg-background px-2 text-sm"
        >
            <option value="" disabled>Another household…</option>
            <option
                v-for="option in candidates"
                :key="option.id"
                :value="option.id"
            >
                {{ option.name }}
            </option>
        </select>
        <Input
            v-model="quantity"
            inputmode="decimal"
            placeholder="Quantity"
            :aria-label="`Quantity of ${product.name}`"
            class="w-28"
        />
        <Button
            type="submit"
            variant="outline"
            size="sm"
            :disabled="processing"
        >
            Allocate
        </Button>
    </form>
</template>
