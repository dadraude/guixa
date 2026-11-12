<?php

use Guixa\Dashboard\Domain\BasketWorkRepository;
use Guixa\Dashboard\Domain\Error\ReadUnavailable;
use Guixa\Shared\Domain\Role;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

it('sends the successful-read time with readFailed false on a normal read (R15, S11)', function () {
    $this->actingAs(accountWith(Role::Manager));

    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('readFailed', false)
        ->whereType('lastUpdatedAt', 'string')
        ->has('attention')
        ->has('opportunities'));
});

it('sends null sections and time with readFailed true when a known source read fails, never an empty list (R15, S11)', function () {
    $this->actingAs(accountWith(Role::Manager));
    $this->app->instance(BasketWorkRepository::class, new class implements BasketWorkRepository
    {
        public function memberCollection(string $householdId): array
        {
            throw ReadUnavailable::source('baskets');
        }

        public function staffCollection(): array
        {
            throw ReadUnavailable::source('baskets');
        }

        public function staffDrafts(): array
        {
            throw ReadUnavailable::source('baskets');
        }
    });

    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('readFailed', true)
        ->where('lastUpdatedAt', null)
        ->where('attention', null)
        ->where('opportunities', null));
});
