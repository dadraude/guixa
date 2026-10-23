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

    <div class="max-w-xl p-4">
        <Heading :title="`Edit ${product.name}`" />

        <Form
            v-bind="ProductController.update.form(product.id)"
            class="space-y-6"
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
