<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { shallowRef } from 'vue';
import ProductClassificationController from '@/actions/App/Http/Controllers/Catalogue/ProductClassificationController';
import ProducerController from '@/actions/App/Http/Controllers/Catalogue/ProducerController';
import ProducerProductController from '@/actions/App/Http/Controllers/Catalogue/ProducerProductController';
import WeeklyProductController from '@/actions/App/Http/Controllers/Catalogue/WeeklyProductController';
import StockMovementController from '@/actions/App/Http/Controllers/Catalogue/StockMovementController';
import StockProductController from '@/actions/App/Http/Controllers/Catalogue/StockProductController';
import ActiveSwitch from '@/components/ActiveSwitch.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProductAdministrationActions from '@/components/ProductAdministrationActions.vue';
import { Button } from '@/components/ui/button';
import { formatEuroAmount } from '@/lib/formatEuroAmount';
import { formatQuantity } from '@/lib/locale';

type AdministrableProduct = App.Http.ProducerProductRow & {
    can: { edit: boolean; changeClassification: boolean; delete: boolean };
    available: string | null;
};

const props = defineProps<{
    producer: { id: string; name: string };
    products: AdministrableProduct[];
    canCreateStock: boolean;
}>();
const error = shallowRef('');
const processing = shallowRef(false);

function classify(product: AdministrableProduct, stock: boolean) {
    router.put(
        ProductClassificationController.update.url(product.id),
        { stock },
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
                error.value = '';
            },
            onFinish: () => {
                processing.value = false;
            },
            onError: (errors) => {
                error.value =
                    errors.domain ??
                    trans('Could not change the classification.');
            },
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Producers', href: ProducerController.index() }],
    },
});
</script>

<template>
    <Head :title="trans('Products of :name', { name: producer.name })" />
    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="trans('Products of :name', { name: producer.name })"
                :description="
                    trans('Weekly and stock products from this producer')
                "
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    as-child
                    class="h-auto min-h-11 max-w-full min-w-0 scroll-mt-24 py-2 text-center break-words whitespace-normal"
                >
                    <Link :href="WeeklyProductController.create(producer.id)">{{
                        trans('Add weekly product')
                    }}</Link>
                </Button>
                <Button
                    v-if="canCreateStock"
                    as-child
                    variant="outline"
                    class="h-auto min-h-11 max-w-full min-w-0 scroll-mt-24 py-2 text-center break-words whitespace-normal"
                >
                    <Link :href="StockProductController.create(producer.id)">{{
                        trans('Add stock product')
                    }}</Link>
                </Button>
            </div>
        </div>
        <InputError :message="error" role="alert" />
        <p
            v-if="products.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            {{ trans('No products for this producer yet.') }}
        </p>
        <ul v-else class="pilot-list">
            <li v-for="product in products" :key="product.id" class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="font-semibold break-words">
                            {{ product.name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                product.stock
                                    ? trans('Stock product')
                                    : trans('Weekly product')
                            }}
                            · {{ product.unit }} ·
                            {{ formatEuroAmount(product.price) }}
                        </p>
                        <p
                            v-if="product.available !== null"
                            class="text-sm text-muted-foreground"
                            data-test="stock-available"
                        >
                            {{
                                trans(':quantity :unit available', {
                                    quantity: formatQuantity(product.available),
                                    unit: product.unit,
                                })
                            }}
                            ·
                            <Link
                                class="underline"
                                :href="StockMovementController.show(product.id)"
                                :aria-label="`${trans('Movements')}: ${product.name}`"
                                >{{ trans('Movements') }}</Link
                            >
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Form
                            v-if="product.can.edit && !product.stock"
                            v-bind="
                                product.active
                                    ? WeeklyProductController.destroy.form({
                                          producer: product.producerId,
                                          product: product.id,
                                      })
                                    : WeeklyProductController.reactivate.form({
                                          producer: product.producerId,
                                          product: product.id,
                                      })
                            "
                            v-slot="{ processing: switching }"
                            class="pilot-status-switch-form"
                        >
                            <ActiveSwitch
                                :id="product.id"
                                :active="product.active"
                                :name="product.name"
                                :processing="switching"
                            />
                        </Form>
                        <Button
                            v-if="product.can.edit"
                            as-child
                            variant="outline"
                            class="h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
                        >
                            <Link
                                :href="
                                    product.stock
                                        ? StockProductController.edit({
                                              producer: product.producerId,
                                              stockProduct: product.id,
                                          })
                                        : WeeklyProductController.edit({
                                              producer: product.producerId,
                                              product: product.id,
                                          })
                                "
                                :aria-label="`${trans('Edit')}: ${product.name}`"
                            >
                                {{ trans('Edit') }}
                            </Link>
                        </Button>
                    </div>
                </div>
                <ProductAdministrationActions
                    v-if="product.can.changeClassification"
                    :name="product.name"
                    :stock="product.stock"
                    :used="product.used"
                    :can-classify="product.can.changeClassification"
                    :processing="processing"
                    @classify="classify(product, $event)"
                />
            </li>
        </ul>
        <Button
            as-child
            variant="outline"
            class="mt-4 h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
        >
            <Link :href="ProducerProductController.index(producer.id)">{{
                trans('Refresh products')
            }}</Link>
        </Button>
    </div>
</template>
