<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Settings } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, shallowRef } from 'vue';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import CycleParticipantController from '@/actions/App/Http/Controllers/Manage/CycleParticipantController';
import CycleCreateDialog from './CycleCreateDialog.vue';
import CycleManageDialog from './CycleManageDialog.vue';
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cycles', href: CycleController.index() }],
    },
});
const props = defineProps<{
    cycles: App.Http.CycleRow[];
    producers: App.Http.ProducerOptionRow[];
    pickupWeekday: number | null;
    pendingCollections: Record<string, number>;
}>();
const weekdays = [
    trans('Monday'),
    trans('Tuesday'),
    trans('Wednesday'),
    trans('Thursday'),
    trans('Friday'),
    trans('Saturday'),
    trans('Sunday'),
];
const createOpen = shallowRef(false);
const manageOpen = shallowRef(false);
const selectedCycle = shallowRef<App.Http.CycleRow | null>(null);
const selectedParticipant = shallowRef<
    App.Http.CycleRow['participants'][number] | null
>(null);
const dialogTrigger = shallowRef<HTMLElement | null>(null);
const pageDescription = computed(() =>
    props.pickupWeekday === null
        ? trans("Let's organize the next pickup.")
        : trans('The usual pickup is on :weekday.', {
              weekday:
                  weekdays[props.pickupWeekday - 1].toLocaleLowerCase('ca-ES'),
          }),
);

function openCreate(event: MouseEvent) {
    dialogTrigger.value = event.currentTarget as HTMLElement;
    createOpen.value = true;
}
function openManage(
    cycle: App.Http.CycleRow,
    participant: App.Http.CycleRow['participants'][number] | null,
    event: MouseEvent,
) {
    dialogTrigger.value = event.currentTarget as HTMLElement;
    selectedCycle.value = cycle;
    selectedParticipant.value = participant;
    manageOpen.value = true;
}
function missingProducers(cycle: App.Http.CycleRow) {
    return props.producers.filter(
        (producer) =>
            !cycle.participants.some(
                (participant) => participant.producerId === producer.id,
            ),
    );
}
function dateLabel(value: string) {
    const [year, month, day] = value.slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
}
function cutoffLabel(value: string | null) {
    return value
        ? trans(':date at :time', {
              date: dateLabel(value),
              time: value.slice(11, 16),
          })
        : trans('without a scheduled cutoff');
}
</script>

<template>
    <Head :title="trans('Cycles')" />
    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading :title="trans('Cycles')" :description="pageDescription" />
            <Button
                class="min-h-11"
                data-test="open-create-cycle"
                @click="openCreate"
                >{{ trans('Open a cycle') }}</Button
            >
        </div>
        <p
            v-if="cycles.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            {{ trans('No cycles yet.') }}
        </p>
        <ul v-else class="pilot-list space-y-0">
            <li
                v-for="cycle in cycles"
                :key="cycle.id"
                class="grid gap-4"
                :data-test="`cycle-${cycle.id}`"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-4 border-b pb-4"
                >
                    <div class="flex flex-wrap items-center gap-4">
                        <h2 class="text-lg font-semibold">
                            {{
                                trans('Pickup :date', {
                                    date: dateLabel(cycle.pickupDate),
                                })
                            }}
                        </h2>
                        <StatusIndicator
                            :label="
                                cycle.cancelled
                                    ? trans('Cancelled')
                                    : cycle.closed
                                      ? trans('Closed')
                                      : trans('Open')
                            "
                            :tone="
                                cycle.cancelled
                                    ? 'danger'
                                    : cycle.closed
                                      ? 'neutral'
                                      : 'success'
                            "
                            :data-test="
                                cycle.cancelled
                                    ? `cancelled-${cycle.id}`
                                    : undefined
                            "
                        />
                    </div>
                    <div class="pilot-action-row">
                        <Button
                            v-if="(pendingCollections[cycle.id] ?? 0) > 0"
                            as-child
                            variant="outline"
                            class="min-h-11"
                            ><Link
                                :href="
                                    BasketController.index.url({
                                        query: { pending: 1, cycle: cycle.id },
                                    })
                                "
                                data-test="pending-collections"
                                >{{
                                    trans('Pending collections: :count', {
                                        count: String(
                                            pendingCollections[cycle.id],
                                        ),
                                    })
                                }}</Link
                            ></Button
                        >
                        <Button as-child variant="outline" class="min-h-11"
                            ><Link
                                :href="CycleController.show(cycle.id)"
                                :data-test="`summary-${cycle.id}`"
                                >{{ trans('Orders') }}</Link
                            ></Button
                        >
                        <Button
                            v-if="!cycle.cancelled && !cycle.closed"
                            variant="outline"
                            size="icon"
                            class="size-11"
                            :aria-label="
                                trans('Manage cycle for :date', {
                                    date: dateLabel(cycle.pickupDate),
                                })
                            "
                            :title="
                                trans('Manage cycle for :date', {
                                    date: dateLabel(cycle.pickupDate),
                                })
                            "
                            :data-test="`manage-${cycle.id}`"
                            @click="openManage(cycle, null, $event)"
                        >
                            <Settings aria-hidden="true" />
                        </Button>
                    </div>
                </div>
                <p
                    v-if="cycle.participants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ trans('No producer takes part in this cycle yet.') }}
                </p>
                <ul v-else class="divide-y">
                    <li
                        v-for="participant in cycle.participants"
                        :key="participant.producerId"
                        class="flex flex-wrap items-center justify-between gap-2 py-2"
                        :data-test="`participant-${cycle.id}-${participant.producerId}`"
                    >
                        <span class="font-medium">{{
                            participant.producerName
                        }}</span>
                        <span
                            class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                        >
                            <StatusIndicator
                                :label="
                                    participant.open
                                        ? trans('Open')
                                        : trans('Closed')
                                "
                                :tone="participant.open ? 'success' : 'neutral'"
                            />
                            {{ cutoffLabel(participant.cutoff) }}
                        </span>
                        <Button
                            v-if="
                                participant.participating &&
                                !cycle.cancelled &&
                                !cycle.closed
                            "
                            variant="outline"
                            size="icon"
                            class="size-11"
                            :aria-label="
                                trans('Manage :producer · :date', {
                                    producer: participant.producerName,
                                    date: dateLabel(cycle.pickupDate),
                                })
                            "
                            :title="
                                trans('Manage :producer · :date', {
                                    producer: participant.producerName,
                                    date: dateLabel(cycle.pickupDate),
                                })
                            "
                            :data-test="`manage-${cycle.id}-${participant.producerId}`"
                            @click="openManage(cycle, participant, $event)"
                        >
                            <Settings aria-hidden="true" />
                        </Button>
                    </li>
                </ul>
                <details
                    v-if="
                        !cycle.cancelled &&
                        !cycle.closed &&
                        !cycle.settled &&
                        missingProducers(cycle).length
                    "
                    class="border-t pt-2"
                >
                    <summary
                        class="flex min-h-11 cursor-pointer items-center text-sm font-medium"
                    >
                        {{ trans('Add a producer') }}
                    </summary>
                    <div class="pilot-action-row mt-2">
                        <Form
                            v-for="producer in missingProducers(cycle)"
                            :key="producer.id"
                            v-bind="
                                CycleParticipantController.store.form({
                                    cycle: cycle.id,
                                    producer: producer.id,
                                })
                            "
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                class="min-h-11"
                                :disabled="processing"
                                :data-test="`add-${cycle.id}-${producer.id}`"
                                >{{ producer.name }}</Button
                            >
                        </Form>
                    </div>
                </details>
            </li>
        </ul>
        <CycleCreateDialog
            :open="createOpen"
            :return-focus-to="dialogTrigger"
            :producers="producers"
            :pickup-weekday="pickupWeekday"
            @close="createOpen = false"
        />
        <CycleManageDialog
            :open="manageOpen"
            :cycle="selectedCycle"
            :participant="selectedParticipant"
            :return-focus-to="dialogTrigger"
            @close="manageOpen = false"
        />
    </div>
</template>
