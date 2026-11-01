<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import FormSelect from '@/components/FormSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

defineProps<{ households: App.Http.BasketHouseholdRow[] }>();
</script>

<template>
    <Head :title="trans('Create basket')" />
    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Create basket')"
            :description="trans('Select the household taking stock today.')"
        />
        <Link
            :href="BasketController.index.url()"
            class="pilot-back-link scroll-mt-32 text-sm underline"
            data-visual-page-focus-first
        >
            {{ trans('All baskets') }}
        </Link>
        <p
            v-if="households.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="no-households"
        >
            {{ trans('There are no active households.') }}
        </p>
        <section v-else class="pilot-layout-panel max-w-xl">
            <Form
                v-bind="BasketController.store.form()"
                class="pilot-layout-form flex flex-col"
                v-slot="{ errors, processing }"
            >
                <label for="basket-household">{{ trans('Household') }}</label>
                <FormSelect
                    id="basket-household"
                    name="household_id"
                    :options="
                        households.map((household) => ({
                            value: household.id,
                            label: household.name,
                        }))
                    "
                    :placeholder="trans('Choose a household')"
                    required
                    class="w-full"
                    data-test="household-select"
                />
                <InputError :message="errors.household_id || errors.domain" />
                <Button
                    type="submit"
                    class="min-h-11"
                    :disabled="processing"
                    data-test="create-basket"
                    data-visual-page-focus-last
                >
                    {{ trans('Create basket') }}
                </Button>
            </Form>
        </section>
    </div>
</template>
