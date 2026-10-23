<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SettingsController from '@/actions/App/Http/Controllers/Manage/SettingsController';
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
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];
</script>

<template>
    <Head title="Settings" />

    <div class="max-w-xl space-y-8 p-4">
        <Heading
            title="Settings"
            description="How the cooperative works every week"
        />

        <Form
            v-bind="SettingsController.updatePickupWeekday.form()"
            class="flex flex-wrap items-end gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="pickup-weekday">Pickup weekday</Label>
                <select
                    id="pickup-weekday"
                    name="pickup_weekday"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    :value="pickupWeekday ?? ''"
                >
                    <option v-if="pickupWeekday === null" value="" disabled>
                        Not set
                    </option>
                    <option
                        v-for="(name, index) in weekdays"
                        :key="name"
                        :value="index + 1"
                    >
                        {{ name }}
                    </option>
                </select>
                <InputError :message="errors.pickup_weekday || errors.domain" />
            </div>
            <Button
                type="submit"
                :disabled="processing"
                data-test="save-pickup-weekday"
            >
                Save
            </Button>
        </Form>
        <p v-if="pickupWeekday === null" class="text-sm text-muted-foreground">
            Not set: opening a cycle will ask for it.
        </p>
        <p class="text-sm text-muted-foreground">
            Changing it affects only cycles opened afterwards; cycles already
            open keep their pickup date.
        </p>
    </div>
</template>
