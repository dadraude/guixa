<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import Heading from '@/components/Heading.vue';
import MovementHistory from '@/components/stock/MovementHistory.vue';
import { formatQuantity } from '@/lib/locale';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: ProductController.index() },
            { title: 'Movements', href: '#' },
        ],
    },
});

const visit = (url: string) => router.visit(url);

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

        <MovementHistory :history="history" @navigate="visit" />
    </div>
</template>
