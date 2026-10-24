<script setup lang="ts">
import { computed, ref, watch } from 'vue';
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

// Way A would replace the allocations set by hand, so the manager confirms first (R15, S31).
const submit = () => {
    if (props.product.hasManualAllocations) {
        warning.value = true;

        return;
    }

    emit('save', received.value, false);
};

const confirm = () => {
    warning.value = false;
    emit('save', received.value, true);
};
</script>

<template>
    <form
        class="flex flex-wrap items-end gap-2"
        :data-test="`received-form-${product.productId}`"
        @submit.prevent="submit"
    >
        <div class="grid gap-1">
            <Label :for="`received-${product.productId}`">
                Received ({{ product.unit }})
            </Label>
            <Input
                :id="`received-${product.productId}`"
                v-model="received"
                inputmode="decimal"
                class="w-32"
            />
        </div>
        <Button
            type="submit"
            size="sm"
            :disabled="processing"
            :data-test="`save-received-${product.productId}`"
        >
            Set received total
        </Button>
    </form>

    <Dialog :open="warning" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Replace the manual allocations?</DialogTitle>
                <DialogDescription>
                    The allocations of {{ manualHouseholds }} for
                    {{ product.name }} were set by hand. Setting the received
                    total to {{ received }} re-allocates everything from the
                    ordered quantities and those manual allocations will be
                    lost.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button
                        variant="secondary"
                        :data-test="`cancel-overwrite-${product.productId}`"
                    >
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="processing"
                    :data-test="`confirm-overwrite-${product.productId}`"
                    @click="confirm"
                >
                    Replace them
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
