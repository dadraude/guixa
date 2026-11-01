<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { formatMoney, formatQuantity } from '@/lib/locale';
import { trans } from 'laravel-vue-i18n';

defineProps<{ products: App.Http.StockOfferRow[] }>();
</script>

<template>
    <Head :title="trans('Stock catalogue')" />
    <div class="pilot-layout-page mx-auto max-w-4xl">
        <Heading
            :title="trans('Stock catalogue')"
            :description="
                trans('Current stock available from the cooperative.')
            "
        />
        <p
            v-if="products.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="stock-empty"
        >
            {{ trans('No stock products are available yet.') }}
        </p>
        <section v-else :aria-label="trans('Stock catalogue')">
            <ul class="divide-y border-y">
                <li
                    v-for="product in products"
                    :key="product.id"
                    class="grid min-w-0 gap-2 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                    data-test="stock-offer"
                >
                    <div class="min-w-0">
                        <h2 class="font-semibold break-words">
                            {{ product.name }}
                        </h2>
                        <p class="text-sm break-words text-muted-foreground">
                            {{ product.producerName }} · {{ product.unit }} ·
                            {{ formatMoney(product.price) }}
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-2 sm:justify-end"
                    >
                        <span class="font-medium tabular-nums">
                            {{ formatQuantity(product.available) }}
                            {{ product.unit }}
                        </span>
                        <Badge
                            v-if="product.soldOut"
                            variant="secondary"
                            data-test="stock-sold-out"
                        >
                            {{ trans('Sold out') }}
                        </Badge>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
