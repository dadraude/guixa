<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import SettingsController from '@/actions/App/Http/Controllers/Manage/SettingsController';
import FormSelect from '@/components/FormSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: SettingsController.show() }],
    },
});

defineProps<{
    pickupWeekday: number | null;
}>();

const weekdays = [
    trans('Monday'),
    trans('Tuesday'),
    trans('Wednesday'),
    trans('Thursday'),
    trans('Friday'),
    trans('Saturday'),
    trans('Sunday'),
];
const weekdayOptions = weekdays.map((label, index) => ({
    value: String(index + 1),
    label,
}));
</script>

<template>
    <Head :title="trans('Settings')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page max-w-xl">
        <Heading
            :title="trans('Settings')"
            :description="trans('How the cooperative works every week')"
        />

        <Form
            v-bind="SettingsController.updatePickupWeekday.form()"
            class="flex flex-wrap items-end gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="pickup-weekday">{{
                    trans('Pickup weekday')
                }}</Label>
                <FormSelect
                    id="pickup-weekday"
                    name="pickup_weekday"
                    :options="weekdayOptions"
                    :default-value="pickupWeekday?.toString()"
                    :placeholder="trans('Not set')"
                    class="w-full min-w-0 sm:w-fit sm:min-w-44"
                />
                <InputError :message="errors.pickup_weekday || errors.domain" />
            </div>
            <Button
                type="submit"
                :disabled="processing"
                data-test="save-pickup-weekday"
            >
                {{ trans('Save') }}
            </Button>
        </Form>
        <p v-if="pickupWeekday === null" class="text-sm text-muted-foreground">
            {{ trans('Not set: opening a cycle will ask for it.') }}
        </p>
        <p class="text-sm text-muted-foreground">
            {{
                trans(
                    'Changing it affects only cycles opened afterwards; cycles already open keep their pickup date.',
                )
            }}
        </p>
    </div>
</template>
