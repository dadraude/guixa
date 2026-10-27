<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { reactive } from 'vue';
import CycleCancellationController from '@/actions/App/Http/Controllers/Manage/CycleCancellationController';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import CycleCutoffController from '@/actions/App/Http/Controllers/Manage/CycleCutoffController';
import CycleParticipantController from '@/actions/App/Http/Controllers/Manage/CycleParticipantController';
import FormSelect from '@/components/FormSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
const weekdayOptions = weekdays.map((label, index) => ({
    value: String(index + 1),
    label,
}));

const cutoffLabel = (cutoff: string | null) =>
    cutoff === null ? 'no cutoff' : new Date(cutoff).toLocaleString();

const missingProducers = (cycle: App.Http.CycleRow) =>
    props.producers.filter(
        (producer) =>
            !cycle.participants.some(
                (participant) => participant.producerId === producer.id,
            ),
    );

type CutoffDraft = { date: string; time: string };

const cutoffDrafts = reactive<Record<string, CutoffDraft>>({});

function cutoffDraft(cycleId: string, producerId: string): CutoffDraft {
    const key = `${cycleId}:${producerId}`;
    return (cutoffDrafts[key] ??= { date: '', time: '' });
}

function cutoffValue(cycleId: string, producerId: string): string {
    const { date, time } = cutoffDraft(cycleId, producerId);
    return date && time ? `${date}T${time}` : '';
}
</script>

<template>
    <Head title="Cycles" />

    <div class="pilot-page pilot-manager-page">
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
                        <FormSelect
                            id="cycle-weekday"
                            name="pickup_weekday"
                            required
                            :options="weekdayOptions"
                            :default-value="weekdayOptions[0]?.value"
                            class="w-full min-w-0 sm:w-fit sm:min-w-44"
                        />
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
                <div class="flex items-center justify-between gap-2">
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
                    <StatusIndicator
                        v-if="cycle.cancelled"
                        label="Cycle cancelled"
                        tone="danger"
                        :data-test="`cancelled-${cycle.id}`"
                    />
                    <StatusIndicator
                        v-else-if="cycle.closed"
                        label="Cycle closed"
                        tone="neutral"
                    />
                    <template v-else>
                        <StatusIndicator label="Open" tone="success" />
                        <Dialog>
                            <DialogTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :data-test="`cancel-${cycle.id}`"
                                >
                                    Cancel cycle
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    v-bind="
                                        CycleCancellationController.store.form({
                                            cycle: cycle.id,
                                        })
                                    "
                                    class="space-y-6"
                                    v-slot="{ errors, processing }"
                                >
                                    <DialogHeader class="space-y-3">
                                        <DialogTitle>
                                            Cancel the cycle with pickup
                                            {{ cycle.pickupDate }}?
                                        </DialogTitle>
                                        <DialogDescription>
                                            This discards the orders of every
                                            household in this cycle and cannot
                                            be undone. Members will see the
                                            cycle as cancelled.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <InputError :message="errors.domain" />
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button variant="secondary">
                                                Keep cycle
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            :disabled="processing"
                                            :data-test="`confirm-cancel-${cycle.id}`"
                                        >
                                            Cancel cycle
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </template>
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
                            <span class="flex flex-wrap items-center gap-2">
                                <StatusIndicator
                                    :label="
                                        participant.open ? 'Open' : 'Closed'
                                    "
                                    :tone="
                                        participant.open ? 'success' : 'neutral'
                                    "
                                />
                                <span class="text-muted-foreground">
                                    {{ cutoffLabel(participant.cutoff) }}
                                </span>
                            </span>
                        </div>

                        <div
                            v-if="
                                participant.participating &&
                                !cycle.cancelled &&
                                !cycle.closed
                            "
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
                                <div
                                    class="grid w-full min-w-0 grid-cols-1 gap-2"
                                >
                                    <Label
                                        :for="`cutoff-date-${cycle.id}-${participant.producerId}`"
                                    >
                                        {{ trans('Cutoff date') }}
                                    </Label>
                                    <Input
                                        :id="`cutoff-date-${cycle.id}-${participant.producerId}`"
                                        v-model="
                                            cutoffDraft(
                                                cycle.id,
                                                participant.producerId,
                                            ).date
                                        "
                                        :data-test="`cutoff-date-${cycle.id}-${participant.producerId}`"
                                        type="date"
                                        required
                                    />
                                </div>
                                <div
                                    class="grid w-full min-w-0 grid-cols-1 gap-2"
                                >
                                    <Label
                                        :for="`cutoff-time-${cycle.id}-${participant.producerId}`"
                                    >
                                        {{ trans('Cutoff time') }}
                                    </Label>
                                    <Input
                                        :id="`cutoff-time-${cycle.id}-${participant.producerId}`"
                                        v-model="
                                            cutoffDraft(
                                                cycle.id,
                                                participant.producerId,
                                            ).time
                                        "
                                        :data-test="`cutoff-time-${cycle.id}-${participant.producerId}`"
                                        type="time"
                                        required
                                    />
                                </div>
                                <input
                                    type="hidden"
                                    name="cutoff"
                                    :value="
                                        cutoffValue(
                                            cycle.id,
                                            participant.producerId,
                                        )
                                    "
                                />
                                <output
                                    v-if="
                                        cutoffValue(
                                            cycle.id,
                                            participant.producerId,
                                        )
                                    "
                                    :for="`cutoff-date-${cycle.id}-${participant.producerId} cutoff-time-${cycle.id}-${participant.producerId}`"
                                    :data-test="`cutoff-preview-${cycle.id}-${participant.producerId}`"
                                    class="block w-full text-base break-words text-muted-foreground"
                                >
                                    {{
                                        cutoffDraft(
                                            cycle.id,
                                            participant.producerId,
                                        ).date
                                    }}
                                    {{
                                        cutoffDraft(
                                            cycle.id,
                                            participant.producerId,
                                        ).time
                                    }}
                                </output>
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
                    v-for="producer in cycle.cancelled || cycle.closed
                        ? []
                        : missingProducers(cycle)"
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
