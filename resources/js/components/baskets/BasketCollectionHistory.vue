<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed, shallowRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    state: string;
    events: App.Http.BasketCollectionEventRow[];
    editable: boolean;
    processing?: boolean;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{
    submit: [reason: string, correctionId: string];
}>();
const reason = shallowRef('');
const correctionId = shallowRef(crypto.randomUUID());
const original = computed(() =>
    props.events.find((event) => event.type === 'collected'),
);

function submit() {
    if (props.state !== 'collected' || !reason.value.trim()) return;
    emit('submit', reason.value.trim(), correctionId.value);
}

function reset() {
    reason.value = '';
    correctionId.value = crypto.randomUUID();
}

defineExpose({ reset });
</script>

<template>
    <section class="pilot-layout-panel space-y-4" data-test="collection-panel">
        <h2 class="font-medium">{{ trans('Collection history') }}</h2>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">
                    {{ trans('Original collection') }}
                </dt>
                <dd data-test="original-collection" class="wrap-anywhere">
                    <template v-if="original">
                        {{ original.recordedBy }} · {{ original.occurredAt }}
                    </template>
                    <template v-else>{{ trans('Not yet collected') }}</template>
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ trans('Effective collection') }}
                </dt>
                <dd data-test="effective-collection">
                    {{
                        state === 'collected'
                            ? trans('Collected')
                            : trans('Pending collection')
                    }}
                </dd>
            </div>
        </dl>
        <ol
            v-if="events.length"
            class="pilot-list"
            data-test="collection-history"
        >
            <li
                v-for="event in events"
                :key="event.id"
                class="pilot-list-row flex-wrap gap-2"
                :data-test="`collection-event-${event.type}`"
            >
                <span class="min-w-0 wrap-anywhere">
                    {{
                        event.type === 'corrected'
                            ? trans('Collection mark corrected')
                            : trans('Marked collected')
                    }}
                    <template v-if="event.reason">
                        · {{ event.reason }}</template
                    >
                </span>
                <span class="text-sm wrap-anywhere text-muted-foreground">
                    {{ event.recordedBy }} · {{ event.occurredAt }}
                </span>
            </li>
        </ol>
        <form
            v-if="editable && state === 'collected'"
            class="space-y-3"
            data-test="collection-correction-form"
            @submit.prevent="submit"
        >
            <div class="space-y-1">
                <label for="collection-reason">{{
                    trans('Reason for collection correction')
                }}</label>
                <textarea
                    id="collection-reason"
                    v-model="reason"
                    required
                    maxlength="2000"
                    class="min-h-24 w-full rounded-md border bg-background p-3"
                />
                <InputError :message="errors?.reason" />
            </div>
            <Button
                type="submit"
                class="min-h-11"
                :disabled="processing || !reason.trim()"
            >
                {{ trans('Correct collection mark') }}
            </Button>
        </form>
    </section>
</template>
