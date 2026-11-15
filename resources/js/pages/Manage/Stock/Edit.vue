<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { shallowRef } from 'vue';
import ProductClassificationController from '@/actions/App/Http/Controllers/Catalogue/ProductClassificationController';
import ProducerProductController from '@/actions/App/Http/Controllers/Catalogue/ProducerProductController';
import ProductAdministrationActions from '@/components/ProductAdministrationActions.vue';
import InputError from '@/components/InputError.vue';
import ProducerController from '@/actions/App/Http/Controllers/Catalogue/ProducerController';
import StockProductController from '@/actions/App/Http/Controllers/Catalogue/StockProductController';
import Heading from '@/components/Heading.vue';
import StockProductFields from '@/components/StockProductFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Producers', href: ProducerController.index() },
            { title: 'Edit product', href: '#' },
        ],
    },
});

defineProps<{
    product: App.Http.StockProductRow;
    used: boolean;
}>();
const actionError = shallowRef('');
const actionProcessing = shallowRef(false);
function classify(product: App.Http.StockProductRow, stock: boolean) {
    router.put(
        ProductClassificationController.update.url(product.id),
        { stock },
        {
            onStart: () => {
                actionProcessing.value = true;
                actionError.value = '';
            },
            onFinish: () => {
                actionProcessing.value = false;
            },
            onError: (errors) => {
                actionError.value =
                    errors.domain ??
                    trans('Could not change the classification.');
            },
        },
    );
}
function deleteUnused(product: App.Http.StockProductRow) {
    router.delete(
        StockProductController.deleteUnused.url({
            producer: product.producerId,
            stockProduct: product.id,
        }),
        {
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
        },
    );
}
</script>

<template>
    <Head :title="trans('Edit stock product')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page max-w-xl">
        <Heading :title="trans('Edit :name', { name: product.name })" />

        <Form
            v-bind="
                StockProductController.update.form({
                    producer: product.producerId,
                    stockProduct: product.id,
                })
            "
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <StockProductFields
                :product="product"
                :errors="errors"
                :lock-stock-unit="used"
            />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-stock-product"
            >
                {{ trans('Save') }}
            </Button>
        </Form>
        <div class="mt-6 border-t pt-4">
            <InputError :message="actionError" role="alert" />
            <ProductAdministrationActions
                :name="product.name"
                :stock="true"
                :used="used"
                :can-classify="true"
                :can-delete="true"
                :processing="actionProcessing"
                @classify="classify(product, $event)"
                @delete="deleteUnused(product)"
            />
            <Button
                as-child
                variant="outline"
                class="mt-4 h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
            >
                <Link
                    :href="ProducerProductController.index(product.producerId)"
                    >{{ trans('All products from this producer') }}</Link
                >
            </Button>
        </div>
    </div>
</template>
