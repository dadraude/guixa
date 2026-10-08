<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

test('an administrator creates a household and it gets the next number', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)
        ->post(route('households.store'), ['name' => 'Cal Pere'])
        ->assertRedirect(route('households.index'));
    $this->actingAs($administrator)
        ->post(route('households.store'), ['name' => 'Ca la Maria'])
        ->assertRedirect(route('households.index'));

    $households = Household::orderBy('number')->get();

    expect($households->pluck('name')->all())->toBe(['Cal Pere', 'Ca la Maria'])
        ->and($households[1]->number)->toBe($households[0]->number + 1)
        ->and($households->every(fn (Household $household) => $household->is_active))->toBeTrue();
});

test('numbers are not reused after the highest-numbered household is removed', function () {
    $administrator = User::factory()->administrator()->create();
    $this->actingAs($administrator)->post(route('households.store'), ['name' => 'Cal Pere']);
    $this->actingAs($administrator)->post(route('households.store'), ['name' => 'Ca la Maria']);
    $highest = Household::orderByDesc('number')->firstOrFail();

    $highest->delete();
    $this->actingAs($administrator)->post(route('households.store'), ['name' => 'Cal Joan']);

    expect(Household::where('name', 'Cal Joan')->sole()->number)->toBeGreaterThan($highest->number);
});

test('the household number is assigned by the database, whatever the request sends', function () {
    $administrator = User::factory()->administrator()->create();

    $this->actingAs($administrator)
        ->post(route('households.store'), ['name' => 'Cal Pere', 'number' => 99, 'is_active' => false])
        ->assertRedirect(route('households.index'));

    $household = Household::sole();
    expect($household->number)->not->toBe(99)
        ->and($household->is_active)->toBeTrue();
});

test('renaming a household keeps its number, which no request can change', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create(['name' => 'Cal Pere']);
    $number = $household->fresh()->number;

    $this->actingAs($administrator)
        ->patch(route('households.update', $household), ['name' => 'Cal Pere Nou', 'number' => $number + 50])
        ->assertRedirect(route('households.index'));

    $household->refresh();
    expect($household->name)->toBe('Cal Pere Nou')
        ->and($household->number)->toBe($number);
});

test('renaming an inactive household is allowed and keeps it inactive', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->inactive()->create(['name' => 'Cal Pere']);

    $this->actingAs($administrator)
        ->patch(route('households.update', $household), ['name' => 'Cal Pere Nou'])
        ->assertRedirect(route('households.index'));

    expect($household->fresh()->name)->toBe('Cal Pere Nou')
        ->and($household->fresh()->is_active)->toBeFalse();
});

test('the name is required, and trimmed, on create and rename', function () {
    $administrator = User::factory()->administrator()->create();
    $household = Household::factory()->create(['name' => 'Cal Pere']);

    $this->actingAs($administrator)
        ->post(route('households.store'), ['name' => '  '])
        ->assertSessionHasErrors('name');
    $this->actingAs($administrator)
        ->patch(route('households.update', $household), ['name' => ''])
        ->assertSessionHasErrors('name');
    $this->actingAs($administrator)
        ->post(route('households.store'), ['name' => '  Cal Joan  ']);

    expect(Household::count())->toBe(2)
        ->and($household->fresh()->name)->toBe('Cal Pere')
        ->and(Household::where('name', 'Cal Joan')->exists())->toBeTrue();
});

test('the list shows active and inactive households by number', function () {
    $administrator = User::factory()->administrator()->create();
    $first = Household::factory()->create(['name' => 'Cal Pere']);
    $second = Household::factory()->inactive()->create(['name' => 'Ca la Maria']);

    $this->actingAs($administrator)
        ->get(route('households.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('households/Index')
            ->has('households', 2)
            ->where('households.0.id', $first->id)
            ->where('households.0.number', $first->fresh()->number)
            ->where('households.0.name', 'Cal Pere')
            ->where('households.0.is_active', true)
            ->where('households.1.id', $second->id)
            ->where('households.1.number', $second->fresh()->number)
            ->where('households.1.is_active', false));
});

test('a signed-in user who is not an administrator is refused', function () {
    $user = User::factory()->create();
    $household = Household::factory()->create(['name' => 'Cal Pere']);

    $this->actingAs($user)->get(route('households.index'))->assertForbidden();
    $this->actingAs($user)->post(route('households.store'), ['name' => 'Nou'])->assertForbidden();
    $this->actingAs($user)->patch(route('households.update', $household), ['name' => 'Nou'])->assertForbidden();

    expect(Household::count())->toBe(1)
        ->and($household->fresh()->name)->toBe('Cal Pere');
});

test('an inactive member who keeps the administrator profile is refused', function () {
    $inactive = User::factory()->administrator()->create();
    $inactive->member->update(['is_active' => false]);

    $this->actingAs($inactive)->get(route('households.index'))->assertRedirect(route('login'));
});

test('every household check goes through the manage-membership rule', function () {
    $user = User::factory()->create();
    $administrator = User::factory()->administrator()->create();

    expect(Gate::forUser($user)->allows('manage-membership'))->toBeFalse()
        ->and(Gate::forUser($administrator)->allows('manage-membership'))->toBeTrue();
});

test('the household screen text is in Catalan', function () {
    foreach (['Households', 'Add household', 'Household number', 'Rename', 'Number'] as $key) {
        expect(__($key))->not->toBe($key);
    }
});
