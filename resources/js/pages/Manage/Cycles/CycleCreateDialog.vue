<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NativeDialog from '@/components/manager/NativeDialog.vue';

defineProps<{
    open: boolean;
    returnFocusTo: HTMLElement | null;
    producers: App.Http.ProducerOptionRow[];
    pickupWeekday: number | null;
}>();
const emit = defineEmits<{ close: [] }>();
const weekdays = [
    'Dilluns',
    'Dimarts',
    'Dimecres',
    'Dijous',
    'Divendres',
    'Dissabte',
    'Diumenge',
];
const weekdayOptions = weekdays.map((label, index) => ({
    value: String(index + 1),
    label,
}));
</script>

<template>
    <NativeDialog
        :open="open"
        title="Obre un cicle"
        title-id="create-cycle-title"
        :return-focus-to="returnFocusTo"
        @close="emit('close')"
    >
        <Form
            v-bind="CycleController.store.form()"
            class="space-y-4"
            @success="emit('close')"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="pickup-date">Data de recollida</Label>
                <Input
                    id="pickup-date"
                    name="pickup_date"
                    type="date"
                    required
                    autofocus
                />
                <InputError :message="errors.pickup_date" />
            </div>
            <template v-if="pickupWeekday === null">
                <div class="grid gap-2">
                    <Label for="cycle-weekday">Dia de recollida</Label>
                    <FormSelect
                        id="cycle-weekday"
                        name="pickup_weekday"
                        required
                        :options="weekdayOptions"
                        :default-value="weekdayOptions[0]?.value"
                        class="w-full"
                    />
                    <InputError :message="errors.pickup_weekday" />
                </div>
                <fieldset class="grid gap-1">
                    <legend class="text-sm font-medium">
                        Aquest dia serà permanent?
                    </legend>
                    <label class="flex items-center gap-2"
                        ><input
                            type="radio"
                            name="permanent"
                            value="1"
                            required
                        />
                        Sí, per a la cooperativa</label
                    >
                    <label class="flex items-center gap-2"
                        ><input type="radio" name="permanent" value="0" /> Només
                        per a aquest cicle</label
                    >
                    <InputError :message="errors.permanent" />
                </fieldset>
            </template>
            <fieldset class="grid gap-1">
                <legend class="text-sm font-medium">
                    Productors participants
                </legend>
                <p
                    v-if="producers.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No hi ha productors actius.
                </p>
                <label
                    v-for="producer in producers"
                    :key="producer.id"
                    class="flex items-center gap-2"
                >
                    <input
                        type="checkbox"
                        name="producer_ids[]"
                        :value="producer.id"
                    />
                    {{ producer.name }}
                </label>
                <InputError :message="errors.producer_ids" />
            </fieldset>
            <InputError :message="errors.domain" />
            <div class="flex flex-wrap justify-end gap-2 pt-2">
                <button
                    type="button"
                    class="manager-button manager-button-secondary"
                    @click="emit('close')"
                >
                    Cancel·la
                </button>
                <button
                    type="submit"
                    class="manager-button"
                    :disabled="processing"
                    data-test="open-cycle"
                >
                    Obre el cicle
                </button>
            </div>
        </Form>
    </NativeDialog>
</template>
