<script setup lang="ts">
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

const basketUrl = (basket: string) => BasketController.show.url({ basket });

// A9: the page navigates. Links keep their real href (new tab, copy link) and only a
// plain left click asks the page to visit it.
const emit = defineEmits<{ navigate: [url: string] }>();

const follow = (event: MouseEvent, url: string) => {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    )
        return;

    event.preventDefault();
    emit('navigate', url);
};

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
            v-if="history.total === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="movement-history-empty"
        >
            {{ trans('No movements yet.') }}
        </p>

        <p
            v-else-if="history.lines.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="movement-history-page-empty"
        >
            {{ trans('No movements on this page.') }}
        </p>

        <template v-if="history.lines.length > 0">
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
                                <a
                                    v-if="line.basketId"
                                    :href="basketUrl(line.basketId)"
                                    class="inline-flex min-h-11 max-w-full items-center underline"
                                    data-test="movement-basket-link"
                                    @click="
                                        follow($event, basketUrl(line.basketId))
                                    "
                                >
                                    {{ trans('Basket') }}
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

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
                <a
                    :href="history.prevUrl"
                    rel="prev"
                    @click="follow($event, history.prevUrl)"
                    >{{ trans('Previous page') }}</a
                >
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
                <a
                    :href="history.nextUrl"
                    rel="next"
                    @click="follow($event, history.nextUrl)"
                    >{{ trans('Next page') }}</a
                >
            </Button>
            <span v-else aria-hidden="true"></span>
        </nav>
    </section>
</template>
