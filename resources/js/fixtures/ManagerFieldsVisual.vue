<script setup lang="ts">
import FormSelect from '@/components/FormSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const query = new URLSearchParams(window.location.search);
const invalid = query.has('invalid');
const disabled = query.has('disabled');
const householdOptions = [
    { value: 'casa-1', label: 'Casa 1' },
    { value: 'casa-2', label: 'Casa 2' },
];
</script>

<template>
    <main
        class="pilot-theme coop-manager-shell min-h-screen bg-background text-foreground"
    >
        <div
            class="pilot-page pilot-layout-page pilot-manager-page"
            data-visual-root
        >
            <header class="grid gap-1">
                <h1 class="text-xl font-semibold">Households</h1>
                <p class="text-sm text-muted-foreground">
                    Create households and invite their members
                </p>
            </header>
            <form class="visual-panel flex items-end gap-2" @submit.prevent>
                <div class="grid gap-2">
                    <Label for="fixture-name">New household</Label>
                    <Input
                        id="fixture-name"
                        name="name"
                        placeholder="Name"
                        :aria-invalid="invalid ? 'true' : undefined"
                        :disabled="disabled"
                    />
                    <p v-if="invalid" class="text-sm text-destructive">
                        A household needs a name.
                    </p>
                </div>
                <Button id="fixture-create" type="submit" :disabled="disabled"
                    >Create</Button
                >
            </form>
            <form class="visual-panel flex items-end gap-2" @submit.prevent>
                <div class="grid gap-2">
                    <Label for="fixture-household">Household</Label>
                    <FormSelect
                        id="fixture-household"
                        name="household_id"
                        :options="householdOptions"
                        default-value="casa-1"
                        :disabled="disabled"
                    />
                </div>
                <Button type="button" variant="outline" :disabled="disabled"
                    >Manage</Button
                >
            </form>
        </div>
    </main>
</template>
