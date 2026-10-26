<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { shallowRef, watch } from 'vue';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NativeDialog from '@/components/manager/NativeDialog.vue';

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
        error.value =
            'Introdueix una quantitat vàlida amb un màxim de tres decimals.';
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
                    'No s’ha pogut desar el recompte.';
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
            error.value = errors.domain ?? 'No s’ha pogut canviar l’estat.';
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
        :title="`Edita ${product?.name ?? 'producte'}`"
        title-id="stock-edit-title"
        :return-focus-to="returnFocusTo"
        @close="emit('close')"
    >
        <template v-if="product">
            <p class="text-sm text-muted-foreground">
                {{ product.unit }} · {{ product.price }} € ·
                {{ product.active ? 'Actiu' : 'Inactiu' }}
            </p>
            <form class="mt-4 space-y-4" @submit.prevent="saveAvailable">
                <div class="grid gap-2">
                    <Label for="stock-available"
                        >Quantitat disponible ({{ product.unit }})</Label
                    >
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
                        Cancel·la
                    </button>
                    <button
                        type="submit"
                        class="manager-button"
                        :disabled="processing"
                        data-test="save-available"
                    >
                        Desa la quantitat
                    </button>
                </div>
            </form>
            <details class="mt-5 border-t pt-2">
                <summary
                    class="flex min-h-11 cursor-pointer items-center font-medium"
                >
                    Estat i historial
                </summary>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <Link
                        :href="StockMovementController.show(product.id)"
                        class="manager-button manager-button-secondary"
                        >Moviments</Link
                    >
                    <button
                        type="button"
                        class="manager-button manager-button-secondary"
                        :disabled="processing"
                        @click="confirmStatus = true"
                    >
                        {{ product.active ? 'Desactiva' : 'Reactiva' }}
                    </button>
                </div>
                <div
                    v-if="confirmStatus"
                    class="mt-3 rounded-lg border border-destructive p-3"
                >
                    <p class="text-sm">
                        {{
                            product.active
                                ? 'Vols desactivar aquest producte? Deixarà d’estar disponible per a noves recollides.'
                                : 'Vols reactivar aquest producte?'
                        }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="manager-button manager-button-secondary"
                            @click="confirmStatus = false"
                        >
                            Torna
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
                                    ? 'Confirma la desactivació'
                                    : 'Confirma la reactivació'
                            }}
                        </button>
                    </div>
                </div>
            </details>
        </template>
    </NativeDialog>
</template>
