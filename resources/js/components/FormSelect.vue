<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface FormSelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}

const props = defineProps<{
    options: FormSelectOption[];
    modelValue?: string | null;
    defaultValue?: string | null;
    placeholder?: string;
    name?: string;
    required?: boolean;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
    contentClass?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

defineOptions({ inheritAttrs: false });

const labelFor = (value: unknown): string =>
    props.options.find((option) => option.value === value)?.label ??
    props.placeholder ??
    '';
</script>

<template>
    <Select
        v-slot="{ modelValue: currentValue }"
        :model-value="modelValue"
        :default-value="defaultValue"
        :name="name"
        :required="required"
        :disabled="disabled"
        @update:model-value="emit('update:modelValue', String($event))"
    >
        <SelectTrigger
            v-bind="$attrs"
            :class="
                cn(
                    'min-h-11 max-w-full min-w-0 text-left whitespace-normal data-[size=default]:h-auto *:data-[slot=select-value]:line-clamp-none',
                    props.class,
                )
            "
        >
            <SelectValue :placeholder="placeholder">{{
                labelFor(currentValue)
            }}</SelectValue>
        </SelectTrigger>
        <SelectContent :class="cn('max-h-64', props.contentClass)">
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
                :disabled="option.disabled"
                class="min-h-11"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
