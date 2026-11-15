<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { shallowRef } from 'vue';
import ProducerController from '@/actions/App/Http/Controllers/Catalogue/ProducerController';
import ProducerProductController from '@/actions/App/Http/Controllers/Catalogue/ProducerProductController';
import WeeklyProductController from '@/actions/App/Http/Controllers/Catalogue/WeeklyProductController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProductAdministrationActions from '@/components/ProductAdministrationActions.vue';
import WeeklyProductFields from '@/components/WeeklyProductFields.vue';

const props = defineProps<{
    producer: { id: string; name: string };
    product: App.Http.ProductRow;
    used: boolean;
    canDelete: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Producers', href: ProducerController.index() }],
    },
});

const actionError = shallowRef('');
const actionProcessing = shallowRef(false);

function deleteUnused() {
    router.delete(
        WeeklyProductController.deleteUnused.url({
            producer: props.producer.id,
            product: props.product.id,
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
    <Head :title="trans('Edit product')" />

    <div class="pilot-layout-page pilot-t44-page max-w-3xl">
        <Heading
            :title="trans('Edit :name', { name: product.name })"
            :description="
                trans('Weekly product of :name', { name: producer.name })
            "
        />

        <Form
            v-bind="
                WeeklyProductController.update.form({
                    producer: producer.id,
                    product: product.id,
                })
            "
            class="pilot-layout-panel pilot-layout-form max-w-xl"
            v-slot="{ errors, processing }"
        >
            <WeeklyProductFields
                :product="product"
                :errors="errors"
                :processing="processing"
                :submit-label="trans('Save')"
            />
        </Form>
        <div v-if="canDelete" class="pilot-layout-panel mt-4 max-w-xl">
            <InputError :message="actionError" role="alert" />
            <ProductAdministrationActions
                :name="product.name"
                :stock="false"
                :used="used"
                :can-delete="true"
                :processing="actionProcessing"
                @delete="deleteUnused"
            />
        </div>
        <Link
            :href="ProducerProductController.index(producer.id)"
            class="inline-flex min-h-11 items-center text-sm underline"
            >{{ trans('Back to products') }}</Link
        >
    </div>
</template>
