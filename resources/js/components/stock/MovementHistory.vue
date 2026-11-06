<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    formatEventDateTime,
    formatPickupDate,
    formatQuantity,
} from '@/lib/locale';

const props = defineProps<{
    history: App.Http.StockMovementPageRow;
}>();

const typeLabels: Record<string, string> = {
    entry: trans('stock.movement_types.entry'),
    adjustment: trans('stock.movement_types.adjustment'),
    sale: trans('stock.movement_types.sale'),
    correction: trans('stock.movement_types.correction'),
};

// A producer's payload carries no basket, so the column would stay empty.
const showBaskets = computed(() =>
    props.history.lines.some((line) => line.basketId !== null),
);
</script>

<template>
    <section class="pilot-layout-panel" aria-labelledby="movement-history">
        <h2 id="movement-history" class="pilot-heading text-lg font-medium">
            {{ trans('Movement history') }}
        </h2>

        <p
            v-if="history.lines.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="movement-history-empty"
        >
            {{ trans('No movements yet.') }}
        </p>

        <template v-else>
            <div class="pilot-wallet-movements">
                <table
                    class="pilot-data-table pilot-wallet-table w-full text-sm"
                    data-test="movement-history"
                >
                    <thead>
                        <tr class="border-b text-left">
                            <th scope="col" class="py-2">
                                {{ trans('Date') }}
                            </th>
                            <th scope="col" class="py-2">
                                {{ trans('Type') }}
                            </th>
                            <th scope="col" class="py-2 text-right">
                                {{ trans('Quantity') }}
                            </th>
                            <th scope="col" class="py-2 text-right">
                                {{ trans('Available after') }}
                            </th>
                            <th scope="col" class="py-2">
                                {{ trans('Note') }}
                            </th>
                            <th scope="col" class="py-2">
                                {{ trans('Author') }}
                            </th>
                            <th scope="col" class="py-2">
                                {{ trans('Cycle') }}
                            </th>
                            <th v-if="showBaskets" scope="col" class="py-2">
                                {{ trans('Source') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="line in history.lines"
                            :key="line.id"
                            class="border-b"
                            data-test="movement-line"
                        >
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Date') }}</span
                                >
                                {{ formatEventDateTime(line.occurredAt) }}
                            </td>
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Type') }}</span
                                >
                                <Badge variant="outline">{{
                                    typeLabels[line.type] ?? line.type
                                }}</Badge>
                            </td>
                            <td class="pilot-amount py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Quantity') }}</span
                                >
                                {{ formatQuantity(line.quantity) }}
                            </td>
                            <td class="pilot-amount py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Available after') }}</span
                                >
                                {{ formatQuantity(line.balanceAfter) }}
                            </td>
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Note') }}</span
                                >{{ line.note }}
                            </td>
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Author') }}</span
                                >{{ line.authorName }}
                            </td>
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Cycle') }}</span
                                ><template v-if="line.pickupDate">{{
                                    trans('Pickup :date', {
                                        date: formatPickupDate(line.pickupDate),
                                    })
                                }}</template>
                            </td>
                            <td v-if="showBaskets" class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Source') }}</span
                                >
                                <Link
                                    v-if="line.basketId"
                                    :href="
                                        BasketController.show.url({
                                            basket: line.basketId,
                                        })
                                    "
                                    class="inline-flex min-h-11 max-w-full items-center underline"
                                    data-test="movement-basket-link"
                                >
                                    {{ trans('Basket') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="history.prevUrl || history.nextUrl"
                class="mt-4 flex flex-wrap items-center justify-between gap-2"
                :aria-label="trans('Pages')"
                data-test="movement-paging"
            >
                <Button
                    v-if="history.prevUrl"
                    variant="outline"
                    class="min-h-11"
                    as-child
                >
                    <Link :href="history.prevUrl" rel="prev">{{
                        trans('Previous page')
                    }}</Link>
                </Button>
                <span v-else aria-hidden="true"></span>
                <span class="text-sm text-muted-foreground">{{
                    trans('Page :page', { page: String(history.page) })
                }}</span>
                <Button
                    v-if="history.nextUrl"
                    variant="outline"
                    class="min-h-11"
                    as-child
                >
                    <Link :href="history.nextUrl" rel="next">{{
                        trans('Next page')
                    }}</Link>
                </Button>
                <span v-else aria-hidden="true"></span>
            </nav>
        </template>
    </section>
</template>
