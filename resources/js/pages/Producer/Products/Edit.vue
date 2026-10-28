<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import Heading from '@/components/Heading.vue';
import StockProductFields from '@/components/StockProductFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: ProductController.index() },
            { title: 'Edit product', href: '#' },
        ],
    },
});

defineProps<{
    product: App.Http.ProductRow;
}>();
</script>

<template>
    <Head :title="trans('Edit product')" />

    <div class="pilot-layout-page pilot-t44-page max-w-3xl">
        <Heading :title="trans('Edit :name', { name: product.name })" />

        <Form
            v-bind="ProductController.update.form(product.id)"
            class="pilot-layout-panel pilot-layout-form max-w-xl"
            v-slot="{ errors, processing }"
        >
            <StockProductFields :product="product" :errors="errors" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-product"
            >
                {{ trans('Save') }}
            </Button>
        </Form>
    </div>
</template>
