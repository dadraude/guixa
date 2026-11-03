<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatQuantity } from '@/lib/locale';

const props = defineProps<{
    product: App.Http.ReceptionProductRow;
    processing: boolean;
}>();

const emit = defineEmits<{
    save: [received: string, overwriteManual: boolean];
}>();

const received = ref(props.product.received);
const warning = ref(false);

watch(
    () => props.product.received,
    (value) => (received.value = value),
);

const manualHouseholds = computed(() =>
    props.product.allocations
        .filter((allocation) => allocation.byHand)
        .map((allocation) => allocation.householdName)
        .join(', '),
);

// Cancelling or closing the warning changes nothing, so the box shows the stored total again (S31).
const onOpenChange = (open: boolean) => {
    warning.value = open;

    if (!open) {
        received.value = props.product.received;
    }
};

const save = (overwriteManual: boolean) => {
    warning.value = false;
    emit('save', received.value, overwriteManual);
};

const submit = () => {
    if (props.product.hasManualAllocations) {
        warning.value = true;
        return;
    }
    save(false);
};

const confirm = () => save(true);
</script>

<template>
    <form
        class="flex flex-wrap items-end gap-2"
        :data-test="`received-form-${product.productId}`"
        @submit.prevent="submit"
    >
        <div class="grid max-w-full min-w-0 grid-cols-1 gap-1">
            <Label :for="`received-${product.productId}`">
                {{ trans('Received (:unit)', { unit: product.unit }) }}
            </Label>
            <Input
                :id="`received-${product.productId}`"
                v-model="received"
                inputmode="decimal"
                class="w-32 max-w-full"
            />
        </div>
        <Button
            type="submit"
            size="sm"
            class="h-auto min-h-11 max-w-full whitespace-normal"
            :disabled="processing"
            :data-test="`save-received-${product.productId}`"
        >
            {{ trans('Set received total') }}
        </Button>
    </form>

    <Dialog :open="warning" @update:open="onOpenChange">
        <DialogContent :show-close-button="false">
            <DialogHeader class="space-y-3">
                <DialogTitle>{{
                    trans('Replace the manual allocations?')
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        trans(
                            'The allocations of :households for :product were set by hand. Setting the received total to :received re-allocates everything from the ordered quantities and those manual allocations will be lost.',
                            {
                                households: manualHouseholds,
                                product: product.name,
                                received: formatQuantity(received),
                            },
                        )
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button
                        variant="secondary"
                        :data-test="`cancel-overwrite-${product.productId}`"
                    >
                        {{ trans('Cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="processing"
                    :data-test="`confirm-overwrite-${product.productId}`"
                    @click="confirm"
                >
                    {{ trans('Replace them') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
