<script setup lang="ts">
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
    <Head title="Edit product" />

    <div class="pilot-page max-w-3xl">
        <Heading :title="`Edit ${product.name}`" />

        <Form
            v-bind="ProductController.update.form(product.id)"
            class="pilot-panel max-w-xl space-y-6 p-5 sm:p-6"
            v-slot="{ errors, processing }"
        >
            <StockProductFields :product="product" :errors="errors" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-product"
            >
                Save
            </Button>
        </Form>
    </div>
</template>
