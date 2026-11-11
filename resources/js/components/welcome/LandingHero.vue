<script setup lang="ts">
import { ShoppingBasket } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { Button } from '@/components/ui/button';
</script>

<template>
    <section class="welcome-hero" aria-labelledby="welcome-title">
        <div class="welcome-hero-copy">
            <p class="welcome-eyebrow">
                {{ trans('Agroecological consumer cooperative · Manresa') }}
            </p>
            <h1 id="welcome-title">
                {{ trans('What we eat can transform our world.') }}
            </h1>
            <p class="welcome-intro">
                {{
                    trans(
                        'We are La Guixa. We organise to consume consciously and build community.',
                    )
                }}
            </p>
            <div class="welcome-hero-actions">
                <Button as-child size="lg" class="welcome-primary-action">
                    <a href="#contact">{{ trans('Come and meet us') }}</a>
                </Button>
                <a class="welcome-text-action" href="#how-it-works">
                    {{ trans('How it works') }}
                </a>
            </div>
        </div>
        <div class="welcome-hero-art" aria-hidden="true">
            <ShoppingBasket :size="150" :stroke-width="1.15" />
        </div>
    </section>
</template>

<style scoped>
.welcome-hero {
    display: grid;
    align-items: center;
    gap: 1.5rem;
    padding-block: clamp(2.5rem, 8vw, 5rem) clamp(3.5rem, 8vw, 5.75rem);
}

.welcome-hero-copy {
    min-width: 0;
}

.welcome-eyebrow {
    margin: 0 0 0.75rem;
    color: var(--welcome-blue);
    font-size: 0.8125rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    line-height: 1.5;
    text-transform: uppercase;
}

.welcome-hero h1 {
    max-width: 15ch;
    margin: 0;
    font-size: clamp(2.125rem, 6.5vw, 3.75rem);
    font-weight: 850;
    letter-spacing: -0.045em;
    line-height: 1.08;
    text-wrap: balance;
}

.welcome-intro {
    max-width: 38rem;
    margin: 1rem 0 0;
    font-size: clamp(1rem, 2vw, 1.1875rem);
    line-height: 1.6;
}

.welcome-hero-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 1.5rem;
    margin-top: 1.5rem;
}

.welcome-primary-action {
    min-height: 2.75rem;
    border: 0;
    border-radius: 0.4rem;
    background: var(--welcome-blue);
    padding-inline: 1.25rem;
    color: #fff;
    font-weight: 750;
    text-decoration: none;
}

.welcome-primary-action:hover {
    background: var(--welcome-blue-hover);
}

:global(.dark) .welcome-primary-action {
    color: #15232a;
}

.welcome-text-action {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    color: var(--welcome-blue);
    font-weight: 700;
    text-underline-offset: 0.25em;
}

.welcome-text-action:hover {
    color: var(--welcome-blue-hover);
}

.welcome-hero-art {
    display: none;
    justify-self: end;
    color: var(--welcome-blue);
}

.welcome-hero :is(a:focus-visible) {
    outline: 3px solid var(--welcome-focus);
    outline-offset: 3px;
}

@media (min-width: 48rem) {
    .welcome-hero {
        grid-template-columns: minmax(0, 1.35fr) minmax(12rem, 0.65fr);
        gap: 2rem;
    }

    .welcome-hero-art {
        display: block;
        padding: 1rem;
    }
}
</style>
