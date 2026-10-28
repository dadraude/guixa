<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CutoffController from '@/actions/App/Http/Controllers/Producer/CutoffController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Default cutoff', href: CutoffController.show() },
        ],
    },
});

defineProps<{
    cutoff: App.Http.CutoffRuleRow;
}>();
</script>

<template>
    <Head title="Default cutoff" />

    <div class="pilot-layout-page pilot-t44-page max-w-3xl">
        <Heading
            title="Default cutoff"
            description="Orders for your products close this long before each pickup. A change only affects cycles where you start participating afterwards."
        />

        <Form
            v-bind="CutoffController.update.form()"
            class="pilot-layout-panel pilot-layout-form max-w-xl"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="days_before">Days before pickup (1 to 6)</Label>
                <Input
                    id="days_before"
                    name="days_before"
                    type="number"
                    min="1"
                    max="6"
                    :default-value="cutoff.daysBefore"
                    required
                />
                <InputError :message="errors.days_before" />
            </div>
            <div class="grid gap-2">
                <Label for="time">Time</Label>
                <Input
                    id="time"
                    name="time"
                    type="time"
                    :default-value="cutoff.time"
                    required
                />
                <InputError :message="errors.time || errors.domain" />
            </div>

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-cutoff"
            >
                Save
            </Button>
        </Form>
    </div>
</template>
