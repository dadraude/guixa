<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { useAppearance } from '@/composables/useAppearance';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';

const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', icon: Sun, label: trans('Light') },
    { value: 'dark', icon: Moon, label: trans('Dark') },
    { value: 'system', icon: Monitor, label: trans('System') },
] as const;

function selectAppearance(value: unknown) {
    if (value === 'light' || value === 'dark' || value === 'system') {
        updateAppearance(value);
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="min-h-11 min-w-11"
                :aria-label="trans('Theme')"
                :title="trans('Theme')"
                data-test="appearance-trigger"
            >
                <Sun v-if="appearance === 'light'" aria-hidden="true" />
                <Moon v-else-if="appearance === 'dark'" aria-hidden="true" />
                <Monitor v-else aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-44">
            <DropdownMenuLabel>{{ trans('Theme') }}</DropdownMenuLabel>
            <DropdownMenuRadioGroup
                :model-value="appearance"
                @update:model-value="selectAppearance"
            >
                <DropdownMenuRadioItem
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                    class="min-h-11 rounded-lg"
                >
                    <component :is="option.icon" aria-hidden="true" />
                    {{ option.label }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
