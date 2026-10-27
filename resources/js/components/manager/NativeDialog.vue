<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
    titleId: string;
    returnFocusTo?: HTMLElement | null;
}>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement | null>(null);
let returnFocus: HTMLElement | null = null;

watch(
    () => props.open,
    async (open) => {
        await nextTick();
        if (!dialog.value) return;
        if (open && !dialog.value.open) {
            returnFocus =
                props.returnFocusTo ?? (document.activeElement as HTMLElement);
            dialog.value.showModal();
        } else if (!open && dialog.value.open) {
            dialog.value.close();
        }
    },
    { flush: 'post' },
);

function onClose() {
    emit('close');
    if (returnFocus?.isConnected) returnFocus.focus();
}
</script>

<template>
    <dialog
        ref="dialog"
        class="manager-dialog"
        :aria-labelledby="titleId"
        @close="onClose"
    >
        <div class="flex items-center justify-between gap-3 border-b pb-3">
            <h2 :id="titleId" class="min-w-0 text-xl font-semibold">
                {{ title }}
            </h2>
            <form method="dialog">
                <button
                    type="submit"
                    class="manager-button manager-button-secondary"
                    aria-label="Tanca el diàleg"
                >
                    ×
                </button>
            </form>
        </div>
        <div class="mt-4"><slot /></div>
    </dialog>
</template>
