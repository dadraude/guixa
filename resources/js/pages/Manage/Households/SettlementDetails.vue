<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import { formatMoney, formatPickupDate } from '@/lib/locale';

defineProps<{ settlement: App.Http.HouseholdSettlementRow }>();

const statusLabel = (status: string) =>
    status === 'draft' ? trans('Draft') : trans('Validated');

const euros = (cents: number) => formatMoney((cents / 100).toFixed(2));
</script>

<template>
    <dl class="grid gap-2 text-sm">
        <div class="flex flex-wrap gap-x-2">
            <dt class="font-medium">{{ trans('Balance') }}:</dt>
            <dd>{{ euros(settlement.balanceCents) }}</dd>
        </div>
        <div>
            <dt class="font-medium">{{ trans('Open baskets') }}:</dt>
            <dd v-if="settlement.openBaskets.length === 0">
                {{ trans('None') }}
            </dd>
            <dd v-else>
                <ul class="mt-2 grid gap-2">
                    <li
                        v-for="basket in settlement.openBaskets"
                        :key="basket.basketId"
                    >
                        <Link
                            :href="BasketController.show(basket.basketId)"
                            class="inline-flex min-h-11 items-center rounded-md underline underline-offset-4"
                        >
                            {{
                                trans('Basket of :date', {
                                    date: formatPickupDate(basket.date),
                                })
                            }}
                            ({{ statusLabel(basket.status) }})
                        </Link>
                    </li>
                </ul>
            </dd>
        </div>
    </dl>
</template>
