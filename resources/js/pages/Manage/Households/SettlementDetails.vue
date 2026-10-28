<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import PickupController from '@/actions/App/Http/Controllers/Manage/PickupController';
import { formatMoney, formatPickupDate } from '@/lib/locale';

defineProps<{ settlement: App.Http.HouseholdSettlementRow }>();

const euros = (cents: number) => formatMoney((cents / 100).toFixed(2));
</script>

<template>
    <dl class="grid gap-2 text-sm">
        <div class="flex flex-wrap gap-x-2">
            <dt class="font-medium">{{ trans('Balance') }}:</dt>
            <dd>{{ euros(settlement.balanceCents) }}</dd>
        </div>
        <div>
            <dt class="font-medium">{{ trans('Pending pickups') }}:</dt>
            <dd v-if="settlement.pendingPickups.length === 0">
                {{ trans('None') }}
            </dd>
            <dd v-else>
                <ul class="mt-2 grid gap-2">
                    <li
                        v-for="pickup in settlement.pendingPickups"
                        :key="pickup.cycleId"
                    >
                        <Link
                            :href="
                                PickupController.show({
                                    cycle: pickup.cycleId,
                                    household: settlement.householdId,
                                })
                            "
                            class="inline-flex min-h-11 items-center rounded-md underline underline-offset-4"
                        >
                            {{
                                trans('Pickup on :date', {
                                    date: formatPickupDate(pickup.pickupDate),
                                })
                            }}
                        </Link>
                    </li>
                </ul>
            </dd>
        </div>
    </dl>
</template>
