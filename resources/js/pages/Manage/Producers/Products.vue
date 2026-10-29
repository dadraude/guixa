<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { shallowRef } from 'vue';
import ProductClassificationController from '@/actions/App/Http/Controllers/Manage/ProductClassificationController';
import ProducerProductController from '@/actions/App/Http/Controllers/Manage/ProducerProductController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProductAdministrationActions from '@/components/ProductAdministrationActions.vue';
import { Button } from '@/components/ui/button';
import { formatEuroAmount } from '@/lib/formatEuroAmount';

const props = defineProps<{
    producer: { id: string; name: string };
    products: App.Http.ProducerProductRow[];
}>();
const error = shallowRef('');
const processing = shallowRef(false);

function classify(product: App.Http.ProducerProductRow, stock: boolean) {
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
        breadcrumbs: [{ title: 'Producers', href: '/manage/producers' }],
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
            <Button
                as-child
                class="h-auto min-h-11 max-w-full min-w-0 scroll-mt-24 py-2 text-center break-words whitespace-normal"
            >
                <Link :href="StockProductController.create(producer.id)">{{
                    trans('Add stock product')
                }}</Link>
            </Button>
        </div>
        <InputError :message="error" role="alert" />
        <p
            v-if="products.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            {{ trans('No products for this producer yet.') }}
        </p>
        <ul v-else class="pilot-list">
            <li v-for="product in products" :key="product.id" class="space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
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
                    </div>
                    <Button
                        v-if="product.stock"
                        as-child
                        variant="outline"
                        class="h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
                    >
                        <Link
                            :href="
                                StockProductController.edit({
                                    producer: product.producerId,
                                    stockProduct: product.id,
                                })
                            "
                        >
                            {{ trans('Edit') }}
                        </Link>
                    </Button>
                </div>
                <ProductAdministrationActions
                    :name="product.name"
                    :stock="product.stock"
                    :used="product.used"
                    :can-classify="true"
                    :processing="processing"
                    @classify="classify(product, $event)"
                />
            </li>
        </ul>
        <Button
            as-child
            variant="outline"
            class="mt-5 h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
        >
            <Link :href="ProducerProductController.index(producer.id)">{{
                trans('Refresh products')
            }}</Link>
        </Button>
    </div>
</template>
