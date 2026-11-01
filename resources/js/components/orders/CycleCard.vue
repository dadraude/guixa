<script setup lang="ts">
import { ArrowRight, CalendarDays } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { formatPickupDate, formatQuantity } from '@/lib/locale';

defineProps<{ cycle: App.Http.MemberCycleRow }>();
</script>

<template>
    <div class="flex items-center gap-4">
        <CalendarDays
            class="size-6 shrink-0 text-muted-foreground"
            aria-hidden="true"
        />
        <div class="min-w-0 flex-1 space-y-2 wrap-anywhere">
            <p class="font-semibold">
                {{
                    trans('Pickup on :date', {
                        date: formatPickupDate(cycle.pickupDate),
                    })
                }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{
                    cycle.lineCount === 0
                        ? trans('No products yet')
                        : transChoice(
                              '{1} :displayCount product|[2,*] :displayCount products',
                              cycle.lineCount,
                              {
                                  displayCount: formatQuantity(
                                      String(cycle.lineCount),
                                  ),
                              },
                          )
                }}
            </p>
        </div>
        <ArrowRight class="size-5 shrink-0" aria-hidden="true" />
    </div>
</template>
