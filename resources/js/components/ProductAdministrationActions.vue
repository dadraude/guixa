<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { shallowRef } from 'vue';
import NativeDialog from '@/components/manager/NativeDialog.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    name: string;
    stock: boolean;
    used: boolean;
    canClassify?: boolean;
    canDelete?: boolean;
    processing?: boolean;
}>();
const emit = defineEmits<{ classify: [stock: boolean]; delete: [] }>();
const pending = shallowRef<'classify' | 'delete' | null>(null);
const trigger = shallowRef<HTMLElement | null>(null);

function ask(action: 'classify' | 'delete', event: MouseEvent) {
    trigger.value = event.currentTarget as HTMLElement;
    pending.value = action;
}

function confirm() {
    if (pending.value === 'classify') emit('classify', !props.stock);
    if (pending.value === 'delete') emit('delete');
    pending.value = null;
}
</script>

<template>
    <div class="space-y-3">
        <p v-if="used" class="text-sm text-muted-foreground">
            {{
                trans(
                    'This product has been used. You can deactivate it, but cannot change its classification or delete it.',
                )
            }}
        </p>
        <div v-else class="flex flex-wrap gap-2">
            <Button
                v-if="canClassify"
                type="button"
                variant="outline"
                class="h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
                :disabled="processing"
                data-test="change-product-classification"
                @click="ask('classify', $event)"
            >
                {{
                    stock
                        ? trans('Change to weekly product')
                        : trans('Change to stock product')
                }}
            </Button>
            <Button
                v-if="canDelete"
                type="button"
                variant="destructive"
                class="h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
                :disabled="processing"
                data-test="delete-unused-product"
                @click="ask('delete', $event)"
            >
                {{ trans('Delete unused product') }}
            </Button>
        </div>
        <NativeDialog
            :open="pending !== null"
            :title="
                pending === 'delete'
                    ? trans('Remove')
                    : trans('Change product type?')
            "
            title-id="product-administration-confirm-title"
            :return-focus-to="trigger"
            @close="pending = null"
        >
            <p class="text-sm text-muted-foreground">
                {{
                    pending === 'delete'
                        ? trans(
                              'This unused product will be removed from the catalogue.',
                          )
                        : trans(
                              'Administration will follow the new product classification.',
                          )
                }}
            </p>
            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
                    data-test="cancel-product-action"
                    @click="pending = null"
                >
                    {{ trans('Cancel') }}
                </Button>
                <Button
                    type="button"
                    class="h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
                    data-test="confirm-product-action"
                    @click="confirm"
                >
                    {{ trans('Confirm') }}
                </Button>
            </div>
        </NativeDialog>
    </div>
</template>
