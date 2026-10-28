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
    save: [
        received: string,
        overwriteManual: boolean,
        includeCompleted?: boolean,
    ];
}>();

const received = ref(props.product.received);
const warning = ref(false);
const choosingScope = ref(false);
const includeCompleted = ref<boolean | undefined>(undefined);

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
        includeCompleted.value = undefined;
        choosingScope.value = false;
    }
};

const save = (overwriteManual: boolean) => {
    warning.value = false;
    if (includeCompleted.value === undefined) {
        emit('save', received.value, overwriteManual);
        return;
    }
    emit('save', received.value, overwriteManual, includeCompleted.value);
};

const confirmManualOrSave = () => {
    choosingScope.value = false;
    if (props.product.hasManualAllocations) {
        warning.value = true;
        return;
    }
    save(false);
};

const submit = () => {
    includeCompleted.value = undefined;
    if (props.product.hasDonePickups) {
        choosingScope.value = true;
        warning.value = true;
        return;
    }
    confirmManualOrSave();
};

const chooseScope = (includeDone: boolean) => {
    includeCompleted.value = includeDone;
    confirmManualOrSave();
};

const confirm = () => save(true);
</script>

<template>
    <form
        class="flex flex-wrap items-end gap-2"
        :data-test="`received-form-${product.productId}`"
        @submit.prevent="submit"
    >
        <div class="grid gap-1">
            <Label :for="`received-${product.productId}`">
                {{ trans('Received (:unit)', { unit: product.unit }) }}
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
            {{ trans('Set received total') }}
        </Button>
    </form>

    <Dialog :open="warning" @update:open="onOpenChange">
        <DialogContent :show-close-button="false">
            <template v-if="choosingScope">
                <DialogHeader>
                    <DialogTitle>{{
                        trans('Which pickups should change?')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        trans(
                            'Unfinished pickups are updated. Including done pickups adds separate adjustment lines and wallet charges or credits; original lines stay unchanged.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col gap-2 sm:flex-col">
                    <Button
                        class="min-h-11 whitespace-normal"
                        @click="chooseScope(false)"
                        >{{ trans('Unfinished pickups only') }}</Button
                    >
                    <Button
                        variant="outline"
                        class="min-h-11 whitespace-normal"
                        @click="chooseScope(true)"
                        >{{ trans('All pickups') }}</Button
                    >
                    <DialogClose as-child
                        ><Button variant="secondary" class="min-h-11">{{
                            trans('Cancel')
                        }}</Button></DialogClose
                    >
                </DialogFooter>
            </template>
            <template v-else>
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
            </template>
        </DialogContent>
    </Dialog>
</template>
