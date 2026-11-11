<script setup lang="ts">
import { MapPin, Sprout, UsersRound } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';

const values = [
    {
        icon: Sprout,
        title: trans('Agroecology'),
        text: trans('Food grown with respect for the land.'),
    },
    {
        icon: MapPin,
        title: trans('Proximity'),
        text: trans('We know the people who grow what we eat.'),
    },
    {
        icon: UsersRound,
        title: trans('Community'),
        text: trans('We organise and make decisions together.'),
    },
];

const steps = [
    {
        number: '01',
        title: trans('Choose'),
        text: trans('Place your order for the week.'),
    },
    {
        number: '02',
        title: trans('We prepare'),
        text: trans('Orders are sent to the producers.'),
    },
    {
        number: '03',
        title: trans('Collect'),
        text: trans('Pick up your basket.'),
    },
];
</script>

<template>
    <div class="welcome-story">
        <section
            id="cooperative"
            class="welcome-section"
            aria-labelledby="values-title"
        >
            <h2 id="values-title">{{ trans('We consume with purpose.') }}</h2>
            <ul class="welcome-values">
                <li
                    v-for="value in values"
                    :key="value.title"
                    data-visual-panel
                >
                    <component
                        :is="value.icon"
                        class="welcome-value-icon"
                        aria-hidden="true"
                    />
                    <div>
                        <h3>{{ value.title }}</h3>
                        <p>{{ value.text }}</p>
                    </div>
                </li>
            </ul>
        </section>

        <section
            id="how-it-works"
            class="welcome-section welcome-process"
            aria-labelledby="process-title"
        >
            <h2 id="process-title">
                {{ trans('From the order to your basket.') }}
            </h2>
            <ol class="welcome-steps">
                <li v-for="step in steps" :key="step.number" data-visual-panel>
                    <span class="welcome-step-number">{{ step.number }}</span>
                    <div>
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.text }}</p>
                    </div>
                </li>
            </ol>
        </section>
    </div>
</template>

<style scoped>
.welcome-story {
    display: grid;
    gap: clamp(3.5rem, 8vw, 5.5rem);
    padding-bottom: clamp(3.5rem, 8vw, 5.5rem);
}

.welcome-section h2 {
    margin: 0;
    font-size: clamp(1.5rem, 3.2vw, 2rem);
    font-weight: 800;
    letter-spacing: -0.035em;
    line-height: 1.2;
    text-wrap: balance;
}

.welcome-values,
.welcome-steps {
    display: grid;
    gap: 1.5rem;
    margin: 1.5rem 0 0;
    padding: 0;
    list-style: none;
}

.welcome-values li {
    display: grid;
    grid-template-columns: 2.75rem minmax(0, 1fr);
    align-items: center;
    gap: 0.875rem;
    min-width: 0;
}

.welcome-value-icon {
    width: 2.5rem;
    height: 2.5rem;
    color: var(--welcome-blue);
    stroke-width: 1.5;
}

.welcome-values h3,
.welcome-steps h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 800;
    line-height: 1.3;
}

.welcome-values p,
.welcome-steps p {
    margin: 0.25rem 0 0;
    font-size: 0.9375rem;
    line-height: 1.5;
}

.welcome-steps {
    gap: 1.5rem;
}

.welcome-steps li {
    display: grid;
    grid-template-columns: 2.25rem minmax(0, 1fr);
    gap: 0.75rem;
    min-width: 0;
}

.welcome-step-number {
    color: var(--welcome-ochre);
    font-size: 1.5rem;
    font-weight: 850;
    line-height: 1.2;
}

@media (min-width: 48rem) {
    .welcome-values,
    .welcome-steps {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 2rem;
    }

    .welcome-values li {
        grid-template-columns: 3rem minmax(0, 1fr);
        gap: 1rem;
    }
}
</style>
