<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { formatPickupDate } from '@/lib/locale';
import { Form } from '@inertiajs/vue3';
import { computed, shallowRef, watch } from 'vue';
import CycleCancellationController from '@/actions/App/Http/Controllers/Manage/CycleCancellationController';
import CycleCutoffController from '@/actions/App/Http/Controllers/Manage/CycleCutoffController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NativeDialog from '@/components/manager/NativeDialog.vue';

const props = defineProps<{
    open: boolean;
    cycle: App.Http.CycleRow | null;
    participant: App.Http.CycleRow['participants'][number] | null;
    returnFocusTo: HTMLElement | null;
}>();
const emit = defineEmits<{ close: [] }>();
const cutoffDate = shallowRef('');
const cutoffTime = shallowRef('');
const pendingAction = shallowRef<'close' | 'cancel' | null>(null);
const cutoffValue = computed(() =>
    cutoffDate.value && cutoffTime.value
        ? `${cutoffDate.value}T${cutoffTime.value}`
        : '',
);

watch(
    () => [props.open, props.participant] as const,
    () => {
        if (!props.open) return;
        const cutoff = props.participant?.cutoff;
        cutoffDate.value = cutoff?.slice(0, 10) ?? '';
        cutoffTime.value = cutoff?.slice(11, 16) ?? '';
        pendingAction.value = null;
    },
);

const target = computed(() => ({
    cycle: props.cycle?.id ?? '',
    producer: props.participant?.producerId ?? '',
}));
</script>

<template>
    <NativeDialog
        :open="open"
        :title="
            participant?.producerName
                ? trans('Manage :producer', {
                      producer: participant.producerName,
                  })
                : trans('Manage cycle')
        "
        title-id="manage-cycle-title"
        :return-focus-to="returnFocusTo"
        @close="emit('close')"
    >
        <template v-if="cycle">
            <p class="text-sm text-muted-foreground">
                {{
                    participant
                        ? trans('Pickup :date · :status', {
                              date: formatPickupDate(cycle.pickupDate),
                              status: participant.cutoff
                                  ? trans('Scheduled cutoff')
                                  : trans('No scheduled cutoff'),
                          })
                        : trans('Pickup :date', {
                              date: formatPickupDate(cycle.pickupDate),
                          })
                }}
            </p>
            <Form
                v-if="
                    participant &&
                    !cycle.closed &&
                    !cycle.cancelled &&
                    participant.participating
                "
                v-bind="CycleCutoffController.update.form(target)"
                class="mt-4 space-y-4"
                @success="emit('close')"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="cutoff-date">{{
                            trans('Cutoff date')
                        }}</Label
                        ><Input
                            id="cutoff-date"
                            v-model="cutoffDate"
                            type="date"
                            required
                            autofocus
                            data-test="cutoff-date"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cutoff-time">{{
                            trans('Cutoff time')
                        }}</Label
                        ><Input
                            id="cutoff-time"
                            v-model="cutoffTime"
                            type="time"
                            required
                            data-test="cutoff-time"
                        />
                    </div>
                </div>
                <input type="hidden" name="cutoff" :value="cutoffValue" />
                <InputError :message="errors.cutoff ?? errors.domain" />
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
                        data-test="set-cutoff"
                    >
                        {{ trans('Save cutoff') }}
                    </button>
                </div>
            </Form>
            <Form
                v-if="participant?.cutoff && !cycle.closed && !cycle.cancelled"
                v-bind="CycleCutoffController.destroy.form(target)"
                class="mt-2"
                @success="emit('close')"
                v-slot="{ processing }"
            >
                <button
                    type="submit"
                    class="manager-button manager-button-secondary"
                    :disabled="processing"
                    data-test="clear-cutoff"
                >
                    {{ trans('Clear cutoff') }}
                </button>
            </Form>
            <details
                v-if="!cycle.closed && !cycle.cancelled"
                class="mt-5 border-t pt-2"
            >
                <summary
                    class="flex min-h-11 cursor-pointer items-center font-medium"
                >
                    {{ trans('Other actions') }}
                </summary>
                <div
                    v-if="pendingAction"
                    class="mt-2 rounded-lg border border-destructive p-3"
                >
                    <p class="text-sm">
                        {{
                            pendingAction === 'cancel'
                                ? trans(
                                      'All orders in the cycle will be cancelled. This cannot be undone.',
                                  )
                                : trans(
                                      "This producer's orders will close now.",
                                  )
                        }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="manager-button manager-button-secondary"
                            @click="pendingAction = null"
                        >
                            {{ trans('Back') }}
                        </button>
                        <Form
                            v-if="pendingAction === 'cancel'"
                            v-bind="
                                CycleCancellationController.store.form({
                                    cycle: cycle.id,
                                })
                            "
                            @success="emit('close')"
                            v-slot="{ errors, processing }"
                        >
                            <InputError :message="errors.domain" /><button
                                type="submit"
                                class="manager-button manager-button-danger"
                                :disabled="processing"
                                :data-test="`confirm-cancel-${cycle.id}`"
                            >
                                {{ trans('Confirm cancellation') }}
                            </button>
                        </Form>
                        <Form
                            v-else-if="participant"
                            v-bind="CycleCutoffController.close.form(target)"
                            @success="emit('close')"
                            v-slot="{ errors, processing }"
                        >
                            <InputError :message="errors.domain" /><button
                                type="submit"
                                class="manager-button manager-button-danger"
                                :disabled="processing"
                                data-test="confirm-close"
                            >
                                {{ trans('Confirm cutoff') }}
                            </button>
                        </Form>
                    </div>
                </div>
                <div v-else class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-if="participant"
                        type="button"
                        class="manager-button manager-button-secondary"
                        @click="pendingAction = 'close'"
                    >
                        {{ trans('Close now') }}
                    </button>
                    <button
                        v-if="!participant"
                        type="button"
                        class="manager-button manager-button-secondary"
                        :data-test="`cancel-${cycle.id}`"
                        @click="pendingAction = 'cancel'"
                    >
                        {{ trans('Cancel cycle') }}
                    </button>
                </div>
            </details>
        </template>
    </NativeDialog>
</template>
