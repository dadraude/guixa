<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatQuantity } from '@/lib/locale';

const props = defineProps<{
    productId: string;
    unit: string;
    allocation: App.Http.ReceptionAllocationRow;
    processing: boolean;
    readonly?: boolean;
}>();

const emit = defineEmits<{
    save: [householdId: string, quantity: string];
}>();

const quantity = ref(props.allocation.allocated);

watch(
    () => props.allocation.allocated,
    (value) => (quantity.value = value),
);
</script>

<template>
    <li
        class="flex flex-wrap items-center justify-between gap-2 py-2"
        :data-test="`allocation-${productId}-${allocation.householdId}`"
    >
        <div class="flex flex-col">
            <span>{{ allocation.householdName }}</span>
            <span class="text-xs text-muted-foreground">
                {{ trans('Ordered') }} {{ formatQuantity(allocation.ordered) }}
                {{ unit }}
                <template v-if="allocation.byHand">
                    {{ trans('· Set by hand') }}</template
                >
            </span>
        </div>
        <span v-if="readonly" data-test="allocated-value">
            {{ formatQuantity(allocation.allocated) }} {{ unit }}
        </span>
        <form
            v-else
            class="flex items-center gap-2"
            @submit.prevent="emit('save', allocation.householdId, quantity)"
        >
            <Input
                v-model="quantity"
                inputmode="decimal"
                :aria-label="
                    trans('Allocation of :name', {
                        name: allocation.householdName,
                    })
                "
                class="w-28"
            />
            <Button
                type="submit"
                variant="outline"
                size="sm"
                :disabled="processing"
            >
                {{ trans('Save') }}
            </Button>
        </form>
    </li>
</template>
