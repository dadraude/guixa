<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import StockController from '@/actions/App/Http/Controllers/Finance/StockController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney, formatQuantity } from '@/lib/locale';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Stock', href: StockController.index() }],
    },
});

defineProps<{
    products: App.Http.StockLevelRow[];
}>();
</script>

<template>
    <Head :title="trans('Stock')" />

    <div class="pilot-layout-page pilot-t44-page">
        <Heading
            :title="trans('Stock')"
            :description="trans('Available quantities and movement history')"
        />

        <p
            v-if="products.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="finance-stock-empty"
        >
            {{ trans('No stock products yet.') }}
        </p>

        <ul v-else class="divide-y border-y">
            <li
                v-for="product in products"
                :key="product.id"
                class="grid min-w-0 gap-2 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                data-test="finance-stock-product"
            >
                <div class="min-w-0">
                    <h2 class="font-medium break-words">{{ product.name }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ product.unit }} · {{ formatMoney(product.price) }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                    <Badge
                        v-if="!product.active"
                        variant="secondary"
                        data-test="finance-stock-inactive"
                    >
                        {{ trans('Inactive') }}
                    </Badge>
                    <span class="tabular-nums">
                        {{ formatQuantity(product.available) }}
                        {{ product.unit }}
                    </span>
                    <Button variant="outline" class="min-h-11" as-child>
                        <Link
                            :href="StockController.movements(product.id)"
                            :aria-label="`${trans('Movement history')}: ${product.name}`"
                        >
                            {{ trans('History') }}
                        </Link>
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
