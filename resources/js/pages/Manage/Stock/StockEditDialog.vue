<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Link, router } from '@inertiajs/vue3';
import { shallowRef, watch } from 'vue';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NativeDialog from '@/components/manager/NativeDialog.vue';
import { formatEuroAmount } from '@/lib/formatEuroAmount';

const props = defineProps<{
    open: boolean;
    product: App.Http.StockLevelRow | null;
    returnFocusTo: HTMLElement | null;
}>();
const emit = defineEmits<{ close: [] }>();
const available = shallowRef('');
const error = shallowRef('');
const processing = shallowRef(false);
const confirmStatus = shallowRef(false);
watch(
    () => [props.open, props.product] as const,
    () => {
        if (!props.open) return;
        available.value = props.product?.available ?? '';
        error.value = '';
        confirmStatus.value = false;
    },
);
function saveAvailable() {
    if (!props.product) return;
    if (!/^\d{1,7}(?:[.,]\d{1,3})?$/.test(available.value.trim())) {
        error.value = trans(
            'Enter a valid quantity with at most three decimal places.',
        );
        return;
    }
    if (available.value.replace(',', '.') === props.product.available) {
        emit('close');
        return;
    }
    router.put(
        StockMovementController.setAvailable.url(props.product.id),
        { available: available.value },
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
            onError: (errors) => {
                error.value =
                    errors.available ??
                    errors.domain ??
                    trans('Could not save the count.');
            },
            onSuccess: () => emit('close'),
        },
    );
}
function changeStatus() {
    if (!props.product) return;
    const options = {
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
        },
        onFinish: () => {
            processing.value = false;
        },
        onError: (errors: Record<string, string>) => {
            error.value =
                errors.domain ?? trans('Could not change the status.');
        },
        onSuccess: () => emit('close'),
    };
    if (props.product.active) {
        router.delete(
            StockProductController.destroy.url(props.product.id),
            options,
        );
    } else {
        router.put(
            StockProductController.reactivate.url(props.product.id),
            {},
            options,
        );
    }
}
</script>

<template>
    <NativeDialog
        :open="open"
        :title="
            product
                ? trans('Edit :name', { name: product.name })
                : trans('Edit product')
        "
        title-id="stock-edit-title"
        :return-focus-to="returnFocusTo"
        @close="emit('close')"
    >
        <template v-if="product">
            <p class="text-sm text-muted-foreground">
                {{ product.unit }} · {{ formatEuroAmount(product.price) }} ·
                {{ product.active ? trans('Active') : trans('Inactive') }}
            </p>
            <form class="mt-4 space-y-4" @submit.prevent="saveAvailable">
                <div class="grid gap-2">
                    <Label for="stock-available">{{
                        trans('Available quantity (:unit)', {
                            unit: product.unit,
                        })
                    }}</Label>
                    <Input
                        id="stock-available"
                        v-model="available"
                        inputmode="decimal"
                        pattern="[0-9]+([.,][0-9]{1,3})?"
                        required
                        autofocus
                        data-test="stock-available"
                    />
                </div>
                <InputError :message="error" />
                <div class="flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        class="manager-button manager-button-secondary"
                        @click="emit('close')"
                    >
                        {{ trans('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="manager-button"
                        :disabled="processing"
                        data-test="save-available"
                    >
                        {{ trans('Save quantity') }}
                    </button>
                </div>
            </form>
            <details class="mt-5 border-t pt-2">
                <summary
                    class="flex min-h-11 cursor-pointer items-center font-medium"
                >
                    {{ trans('Status and history') }}
                </summary>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <Link
                        :href="StockMovementController.show(product.id)"
                        class="manager-button manager-button-secondary"
                        >{{ trans('Movements') }}</Link
                    >
                    <button
                        type="button"
                        class="manager-button manager-button-secondary"
                        :disabled="processing"
                        @click="confirmStatus = true"
                    >
                        {{
                            product.active
                                ? trans('Deactivate')
                                : trans('Reactivate')
                        }}
                    </button>
                </div>
                <div
                    v-if="confirmStatus"
                    class="mt-3 rounded-lg border border-destructive p-3"
                >
                    <p class="text-sm">
                        {{
                            product.active
                                ? trans(
                                      'Deactivate this product? It will no longer be available for new pickups.',
                                  )
                                : trans('Reactivate this product?')
                        }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="manager-button manager-button-secondary"
                            @click="confirmStatus = false"
                        >
                            {{ trans('Back') }}
                        </button>
                        <button
                            type="button"
                            class="manager-button manager-button-danger"
                            :disabled="processing"
                            :data-test="`confirm-status-${product.id}`"
                            @click="changeStatus"
                        >
                            {{
                                product.active
                                    ? trans('Confirm deactivation')
                                    : trans('Confirm reactivation')
                            }}
                        </button>
                    </div>
                </div>
            </details>
        </template>
    </NativeDialog>
</template>
