<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import ReceptionController from '@/actions/App/Http/Controllers/Receptions/ReceptionController';
import Heading from '@/components/Heading.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import { Button } from '@/components/ui/button';
import { formatPickupDate } from '@/lib/locale';

type ReceptionState = 'sent' | 'validated' | 'distributed';

defineProps<{
    cycles: {
        cycleId: string;
        pickupDate: string;
        closed: boolean;
        producers: {
            producerId: string;
            producerName: string;
            state: ReceptionState;
        }[];
    }[];
}>();

const stateLabel = (state: ReceptionState) =>
    ({
        sent: trans('Sent'),
        validated: trans('Validated'),
        distributed: trans('Distributed'),
    })[state];

const stateTone = (state: ReceptionState) =>
    ({ sent: 'warning', validated: 'neutral', distributed: 'success' })[
        state
    ] as 'warning' | 'neutral' | 'success';
</script>

<template>
    <Head :title="trans('Receptions')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Receptions')"
            :description="
                trans('Check what each producer delivered and share it out.')
            "
        />

        <p
            v-if="cycles.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="no-receptions"
        >
            {{ trans('No producer order has been sent yet.') }}
        </p>

        <ul v-else class="pilot-list space-y-0">
            <li
                v-for="cycle in cycles"
                :key="cycle.cycleId"
                class="grid gap-4"
                :data-test="`cycle-${cycle.cycleId}`"
            >
                <div class="grid gap-3 border-b pb-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <h2 class="text-lg font-semibold">
                            {{
                                trans('Pickup :date', {
                                    date: formatPickupDate(cycle.pickupDate),
                                })
                            }}
                        </h2>
                        <StatusIndicator
                            v-if="cycle.closed"
                            :label="trans('Closed')"
                        />
                    </div>
                    <ul class="grid gap-2">
                        <li
                            v-for="producer in cycle.producers"
                            :key="producer.producerId"
                            class="flex flex-wrap items-center justify-between gap-3"
                            :data-test="`producer-${producer.producerId}`"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-medium">{{
                                    producer.producerName
                                }}</span>
                                <StatusIndicator
                                    :label="stateLabel(producer.state)"
                                    :tone="stateTone(producer.state)"
                                />
                            </div>
                            <Button as-child variant="outline" class="min-h-11"
                                ><Link
                                    :href="
                                        ReceptionController.show.url({
                                            cycle: cycle.cycleId,
                                            producer: producer.producerId,
                                        })
                                    "
                                    :aria-label="
                                        trans('Open reception of :producer', {
                                            producer: producer.producerName,
                                        })
                                    "
                                    >{{ trans('Reception') }}</Link
                                ></Button
                            >
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</template>
