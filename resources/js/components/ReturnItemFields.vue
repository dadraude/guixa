<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    item?: App.Http.ReturnItemRow;
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
            :default-value="item?.name"
            required
            :placeholder="trans('Glass jar')"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="amount">{{
            trans('Amount credited per unit (EUR)')
        }}</Label>
        <Input
            id="amount"
            name="amount"
            inputmode="decimal"
            :default-value="item?.amount"
            required
            placeholder="0.50"
        />
        <InputError :message="errors.amount" />
    </div>
</template>
