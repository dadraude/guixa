<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import CycleCutoffController from '@/actions/App/Http/Controllers/Manage/CycleCutoffController';
import CycleParticipantController from '@/actions/App/Http/Controllers/Manage/CycleParticipantController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cycles', href: CycleController.index() }],
    },
});

const props = defineProps<{
    cycles: App.Http.CycleRow[];
    producers: App.Http.ProducerOptionRow[];
    pickupWeekday: number | null;
}>();

const weekdays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];

const cutoffLabel = (cutoff: string | null) =>
    cutoff === null ? 'no cutoff' : new Date(cutoff).toLocaleString();

const missingProducers = (cycle: App.Http.CycleRow) =>
    props.producers.filter(
        (producer) =>
            !cycle.participants.some(
                (participant) => participant.producerId === producer.id,
            ),
    );
</script>

<template>
    <Head title="Cycles" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            title="Cycles"
            :description="
                pickupWeekday === null
                    ? 'No pickup weekday is set yet'
                    : `Pickup is on ${weekdays[pickupWeekday - 1]}`
            "
        />

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-medium">Open a cycle</h2>

            <Form
                v-bind="CycleController.store.form()"
                class="flex flex-col gap-3"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="pickup-date">Pickup date</Label>
                    <Input
                        id="pickup-date"
                        name="pickup_date"
                        type="date"
                        required
                        class="w-fit"
                    />
                    <InputError :message="errors.pickup_date" />
                </div>

                <template v-if="pickupWeekday === null">
                    <div class="grid gap-2">
                        <Label for="cycle-weekday">Pickup weekday</Label>
                        <select
                            id="cycle-weekday"
                            name="pickup_weekday"
                            required
                            class="h-9 w-fit rounded-md border bg-background px-2 text-sm"
                        >
                            <option
                                v-for="(name, index) in weekdays"
                                :key="name"
                                :value="index + 1"
                            >
                                {{ name }}
                            </option>
                        </select>
                        <InputError :message="errors.pickup_weekday" />
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="text-sm font-medium">
                            Is this weekday permanent?
                        </legend>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="permanent"
                                value="1"
                                required
                            />
                            Permanent: it becomes the cooperative's pickup
                            weekday
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="permanent" value="0" />
                            Provisional: only for this cycle
                        </label>
                        <InputError :message="errors.permanent" />
                    </fieldset>
                </template>

                <fieldset class="grid gap-2">
                    <legend class="text-sm font-medium">
                        Participating producers
                    </legend>
                    <p
                        v-if="producers.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        There are no active producers.
                    </p>
                    <label
                        v-for="producer in producers"
                        :key="producer.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="producer_ids[]"
                            :value="producer.id"
                        />
                        {{ producer.name }}
                    </label>
                    <InputError :message="errors.producer_ids" />
                </fieldset>

                <InputError :message="errors.domain" />
                <Button
                    type="submit"
                    class="w-fit"
                    :disabled="processing"
                    data-test="open-cycle"
                >
                    Open cycle
                </Button>
            </Form>
        </section>

        <p v-if="cycles.length === 0" class="text-sm text-muted-foreground">
            No cycles yet.
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="cycle in cycles"
                :key="cycle.id"
                class="flex flex-col gap-3 p-3"
                :data-test="`cycle-${cycle.id}`"
            >
                <div class="flex justify-between gap-2">
                    <span class="font-medium">
                        Pickup {{ cycle.pickupDate }}
                    </span>
                    <Link
                        :href="CycleController.show(cycle.id)"
                        class="text-sm underline"
                        :data-test="`summary-${cycle.id}`"
                    >
                        Orders
                    </Link>
                </div>

                <p
                    v-if="cycle.participants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No producer participates yet.
                </p>
                <ul v-else class="divide-y text-sm">
                    <li
                        v-for="participant in cycle.participants"
                        :key="participant.producerId"
                        class="flex flex-col gap-2 py-2"
                        :data-test="`participant-${cycle.id}-${participant.producerId}`"
                    >
                        <div class="flex justify-between gap-2">
                            <span>{{ participant.producerName }}</span>
                            <span class="text-muted-foreground">
                                {{ participant.open ? 'Open' : 'Closed' }},
                                {{ cutoffLabel(participant.cutoff) }}
                            </span>
                        </div>

                        <div
                            v-if="participant.participating"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <Form
                                v-bind="
                                    CycleCutoffController.update.form({
                                        cycle: cycle.id,
                                        producer: participant.producerId,
                                    })
                                "
                                class="flex items-center gap-2"
                                v-slot="{ errors, processing }"
                            >
                                <Input
                                    name="cutoff"
                                    type="datetime-local"
                                    required
                                    class="w-fit"
                                    :aria-label="`New cutoff for ${participant.producerName}`"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                    :data-test="`set-cutoff-${cycle.id}-${participant.producerId}`"
                                >
                                    Set cutoff
                                </Button>
                                <InputError
                                    :message="errors.cutoff ?? errors.domain"
                                />
                            </Form>
                            <Form
                                v-bind="
                                    CycleCutoffController.close.form({
                                        cycle: cycle.id,
                                        producer: participant.producerId,
                                    })
                                "
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                    :data-test="`close-now-${cycle.id}-${participant.producerId}`"
                                >
                                    Close now
                                </Button>
                            </Form>
                            <Form
                                v-if="participant.cutoff !== null"
                                v-bind="
                                    CycleCutoffController.destroy.form({
                                        cycle: cycle.id,
                                        producer: participant.producerId,
                                    })
                                "
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                    :data-test="`clear-cutoff-${cycle.id}-${participant.producerId}`"
                                >
                                    Clear cutoff
                                </Button>
                            </Form>
                        </div>
                    </li>
                </ul>

                <Form
                    v-for="producer in missingProducers(cycle)"
                    :key="producer.id"
                    v-bind="
                        CycleParticipantController.store.form({
                            cycle: cycle.id,
                            producer: producer.id,
                        })
                    "
                    class="flex items-center gap-2"
                    v-slot="{ errors, processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                        :data-test="`add-${cycle.id}-${producer.id}`"
                    >
                        Add {{ producer.name }}
                    </Button>
                    <InputError :message="errors.domain" />
                </Form>
            </li>
        </ul>
    </div>
</template>
