<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    product?: Pick<
        App.Http.StockProductRow,
        'name' | 'unit' | 'price' | 'variable' | 'containersPerUnit'
    >;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <InputError :message="errors.domain" />

    <div class="grid gap-2">
        <Label for="name">{{ trans('Name') }}</Label>
        <Input
            id="name"
            name="name"
            :default-value="product?.name"
            required
            :placeholder="trans('Honey')"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="unit">{{ trans('Unit') }}</Label>
        <Input
            id="unit"
            name="unit"
            :default-value="product?.unit"
            required
            :placeholder="trans('jar, kg, bottle')"
        />
        <InputError :message="errors.unit" />
    </div>

    <div class="grid gap-2">
        <Label for="price">{{ trans('Price per unit (EUR)') }}</Label>
        <Input
            id="price"
            name="price"
            inputmode="decimal"
            :default-value="product?.price"
            required
            placeholder="6.50"
        />
        <InputError :message="errors.price" />
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="variable" value="0" />
        <input
            id="variable"
            type="checkbox"
            name="variable"
            value="1"
            :checked="product?.variable ?? false"
        />
        <Label for="variable">{{
            trans('Sold by variable quantity (weight or volume)')
        }}</Label>
    </div>

    <div class="grid gap-2">
        <Label for="containers_per_unit">{{
            trans('Containers per unit (optional)')
        }}</Label>
        <Input
            id="containers_per_unit"
            name="containers_per_unit"
            inputmode="decimal"
            :default-value="product?.containersPerUnit ?? ''"
            placeholder="2"
        />
        <InputError :message="errors.containers_per_unit" />
    </div>
</template>
