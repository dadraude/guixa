<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head, router } from '@inertiajs/vue3';
import { shallowRef } from 'vue';
import ProductAdministrationActions from '@/components/ProductAdministrationActions.vue';
import InputError from '@/components/InputError.vue';
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
    used: boolean;
}>();
const actionError = shallowRef('');
const actionProcessing = shallowRef(false);
function deleteUnused(product: App.Http.ProductRow) {
    router.delete(ProductController.deleteUnused.url(product.id), {
        onStart: () => {
            actionProcessing.value = true;
            actionError.value = '';
        },
        onFinish: () => {
            actionProcessing.value = false;
        },
        onError: (errors) => {
            actionError.value =
                errors.domain ?? trans('Could not delete the product.');
        },
    });
}
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
        <div class="pilot-layout-panel mt-5 max-w-xl">
            <InputError :message="actionError" role="alert" />
            <ProductAdministrationActions
                :name="product.name"
                :stock="false"
                :used="used"
                :can-delete="true"
                :processing="actionProcessing"
                @delete="deleteUnused(product)"
            />
        </div>
    </div>
</template>
