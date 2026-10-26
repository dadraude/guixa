<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import CycleParticipantController from '@/actions/App/Http/Controllers/Manage/CycleParticipantController';
import CycleCreateDialog from './CycleCreateDialog.vue';
import CycleManageDialog from './CycleManageDialog.vue';
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cicles', href: CycleController.index() }],
    },
});
const props = defineProps<{
    cycles: App.Http.CycleRow[];
    producers: App.Http.ProducerOptionRow[];
    pickupWeekday: number | null;
}>();
const weekdays = [
    'dilluns',
    'dimarts',
    'dimecres',
    'dijous',
    'divendres',
    'dissabte',
    'diumenge',
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
        ? 'Organitzem la propera recollida.'
        : `La recollida habitual és els ${weekdays[props.pickupWeekday - 1]}.`,
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
        ? `${dateLabel(value)} a les ${value.slice(11, 16)}`
        : 'sense tancament programat';
}
</script>

<template>
    <Head title="Cicles" />
    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading title="Cicles" :description="pageDescription" />
            <Button
                class="min-h-11"
                data-test="open-create-cycle"
                @click="openCreate"
                >Obre un cicle</Button
            >
        </div>
        <p
            v-if="cycles.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            Encara no hi ha cicles.
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
                            Recollida {{ dateLabel(cycle.pickupDate) }}
                        </h2>
                        <span
                            class="rounded-md bg-muted px-2 py-1 text-xs font-semibold"
                            :data-test="
                                cycle.cancelled
                                    ? `cancelled-${cycle.id}`
                                    : undefined
                            "
                        >
                            {{
                                cycle.cancelled
                                    ? 'Cancel·lat'
                                    : cycle.closed
                                      ? 'Tancat'
                                      : 'Obert'
                            }}
                        </span>
                    </div>
                    <div class="pilot-action-row">
                        <Button as-child variant="outline" class="min-h-11"
                            ><Link
                                :href="CycleController.show(cycle.id)"
                                :data-test="`summary-${cycle.id}`"
                                >Comandes</Link
                            ></Button
                        >
                        <Button
                            v-if="!cycle.cancelled && !cycle.closed"
                            variant="outline"
                            class="min-h-11"
                            :data-test="`manage-${cycle.id}`"
                            @click="
                                openManage(
                                    cycle,
                                    cycle.participants[0] ?? null,
                                    $event,
                                )
                            "
                            >Gestiona</Button
                        >
                    </div>
                </div>
                <p
                    v-if="cycle.participants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Encara no hi participa cap productor.
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
                        <span class="text-sm text-muted-foreground"
                            >{{ participant.open ? 'Obert' : 'Tancat' }},
                            {{ cutoffLabel(participant.cutoff) }}</span
                        >
                        <Button
                            v-if="
                                participant.participating &&
                                !cycle.cancelled &&
                                !cycle.closed
                            "
                            variant="outline"
                            size="sm"
                            class="min-h-11"
                            :data-test="`manage-${cycle.id}-${participant.producerId}`"
                            @click="openManage(cycle, participant, $event)"
                            >Gestiona</Button
                        >
                    </li>
                </ul>
                <details
                    v-if="
                        !cycle.cancelled &&
                        !cycle.closed &&
                        missingProducers(cycle).length
                    "
                    class="border-t pt-2"
                >
                    <summary
                        class="flex min-h-11 cursor-pointer items-center text-sm font-medium"
                    >
                        Afegeix un productor
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
