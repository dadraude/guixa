<script setup lang="ts">
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
            <span class="welcome-basket"></span>
        </div>
    </section>
</template>

<style scoped>
.welcome-hero {
    display: grid;
    align-items: center;
    gap: 1.5rem;
    padding-block: clamp(3.5rem, 8vw, 6rem);
}

.welcome-hero-copy {
    min-width: 0;
    background: var(--welcome-hero-surface);
    box-shadow: 0 0 0.75rem 0.5rem var(--welcome-hero-surface);
}

.welcome-eyebrow {
    margin: 0 0 0.75rem;
    color: var(--welcome-hero-cream);
    font-size: 0.8125rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    line-height: 1.5;
    text-transform: uppercase;
    overflow-wrap: anywhere;
}

.welcome-hero h1 {
    max-width: 16ch;
    margin: 0;
    font-size: clamp(2.5rem, 6.5vw, 4.25rem);
    font-weight: 900;
    letter-spacing: -0.045em;
    line-height: 1.08;
    text-wrap: balance;
    overflow-wrap: anywhere;
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
    height: auto;
    border: 0;
    border-radius: 0.4rem;
    background: var(--welcome-hero-cream);
    padding-inline: 1.25rem;
    color: #173e53;
    font-weight: 750;
    text-decoration: none;
    white-space: normal;
}

.welcome-primary-action:hover {
    background: #fff9eb;
}

.welcome-text-action {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    color: var(--welcome-hero-cream);
    font-weight: 700;
    text-underline-offset: 0.25em;
}

.welcome-text-action:hover {
    color: #fff9eb;
}

.welcome-hero-art {
    display: none;
    justify-self: end;
    width: min(100%, 22rem);
    color: var(--welcome-hero-cream);
}

.welcome-basket {
    display: block;
    width: 100%;
    aspect-ratio: 1;
    background: var(--welcome-hero-cream);
    mask-image: url('../../../images/welcome/vegetable-basket.png');
    mask-position: center;
    mask-repeat: no-repeat;
    mask-size: 125%;
}

.welcome-hero :is(a:focus-visible) {
    outline: 3px solid var(--welcome-hero-cream);
    outline-offset: 3px;
}

@container (min-width: 48rem) {
    .welcome-hero {
        grid-template-columns: minmax(0, 1.35fr) minmax(12rem, 0.65fr);
        gap: 2rem;
    }

    .welcome-hero-art {
        display: block;
        padding: 1rem;
    }
}

@media (max-width: 47.999rem) {
    .welcome-hero-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .welcome-text-action {
        align-self: start;
    }
}
</style>
