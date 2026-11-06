<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import StockController from '@/actions/App/Http/Controllers/Finance/StockController';
import Heading from '@/components/Heading.vue';
import MovementHistory from '@/components/stock/MovementHistory.vue';
import { formatQuantity } from '@/lib/locale';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock', href: StockController.index() },
            { title: 'Movements', href: '#' },
        ],
    },
});

defineProps<{
    product: { name: string; unit: string; available: string };
    history: App.Http.StockMovementPageRow;
}>();
</script>

<template>
    <Head :title="trans('Stock movements')" />

    <div class="pilot-layout-page pilot-t44-page">
        <Heading
            :title="product.name"
            :description="
                trans(':quantity :unit available', {
                    quantity: formatQuantity(product.available),
                    unit: product.unit,
                })
            "
        />

        <MovementHistory :history="history" />
    </div>
</template>
