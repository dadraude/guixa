<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormSelect from '@/components/FormSelect.vue';
import { euros } from './euros';
import { formatQuantity } from '@/lib/locale';

const props = defineProps<{
    lines: App.Http.BasketReturnLineRow[];
    options: App.Http.ReturnItemRow[];
    editable: boolean;
    processing: boolean;
    errors?: Record<string, string>;
    emptyMessage?: string;
}>();

const emit = defineEmits<{
    add: [returnItemId: string, quantity: string];
    remove: [returnItemId: string];
}>();

const itemId = ref('');
const quantity = ref('');

const itemOptions = computed(() =>
    props.options.map((option) => ({
        value: option.id,
        label: `${option.name} (${euros(option.amountCents)})`,
    })),
);

const submit = () => {
    if (itemId.value === '' || quantity.value.trim() === '') {
        return;
    }

    emit('add', itemId.value, quantity.value.trim());
    quantity.value = '';
};
</script>

<template>
    <section class="flex flex-col gap-4" data-test="return-lines">
        <h3 class="text-base font-medium">{{ trans('Returns') }}</h3>

        <p
            v-if="lines.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-return-lines"
        >
            {{
                emptyMessage ??
                trans('No returns have been added to this pickup.')
            }}
        </p>

        <ul class="pilot-list">
            <li
                v-for="line in lines"
                :key="line.returnItemId"
                class="pilot-list-row"
                :data-test="`return-line-${line.returnItemId}`"
            >
                <div class="pilot-list-row-body">
                    <span>{{ line.name }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ formatQuantity(String(line.quantity)) }} ×
                        {{ euros(line.amountCents) }}
                    </span>
                </div>
                <span class="pilot-amount" data-test="credit"
                    >−{{ euros(line.creditCents) }}</span
                >
                <Button
                    v-if="editable"
                    type="button"
                    variant="outline"
                    class="min-h-11 min-w-11"
                    :aria-label="trans('Remove :name', { name: line.name })"
                    :data-test="`remove-return-${line.returnItemId}`"
                    :disabled="processing"
                    @click="emit('remove', line.returnItemId)"
                >
                    {{ trans('Remove') }}
                </Button>
            </li>
        </ul>

        <form
            v-if="editable"
            class="flex flex-wrap items-end gap-2"
            data-test="add-return-form"
            @submit.prevent="submit"
        >
            <FormSelect
                v-model="itemId"
                :options="itemOptions"
                :placeholder="trans('Choose an item')"
                class="h-11 min-w-0 flex-1 basis-44"
                :aria-label="trans('Return item')"
                data-test="return-item"
            />
            <Input
                v-model="quantity"
                inputmode="numeric"
                class="h-11 w-24"
                :aria-label="trans('Number of returned items to add')"
                data-test="return-quantity"
            />
            <Button
                type="submit"
                variant="outline"
                class="min-h-11 min-w-11"
                :disabled="processing"
            >
                {{ trans('Add return') }}
            </Button>
        </form>
        <InputError :message="errors?.return_item_id" />
        <InputError
            :message="errors?.return_quantity"
            data-test="quantity-error"
        />
    </section>
</template>
