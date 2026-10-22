<?php

use Guixa\Shared\Domain\Role;
use Guixa\Stock\Infrastructure\Persistence\ReturnItemModel;
use Inertia\Testing\AssertableInertia as Assert;

function glassJar(array $overrides = []): ReturnItemModel
{
    return ReturnItemModel::query()->create(['name' => 'Glass jar', 'amount_cents' => 50, 'active' => true, ...$overrides]);
}

it('keeps the return item pages to managers', function (string $method, string $uri) {
    $item = glassJar();
    $uri = str_replace('{id}', $item->id, $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/manage/return-items'],
    ['get', '/manage/return-items/create'],
    ['post', '/manage/return-items'],
    ['get', '/manage/return-items/{id}/edit'],
    ['put', '/manage/return-items/{id}'],
    ['delete', '/manage/return-items/{id}'],
]);

it('lists the return items with their amount as a decimal string (R25)', function () {
    glassJar();

    $this->actingAs(accountWith(Role::Manager))->get('/manage/return-items')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/ReturnItems/Index')
            ->has('items', 1)
            ->where('items.0.name', 'Glass jar')
            ->where('items.0.amount', '0.50')
            ->where('items.0.active', true));
});

it('creates a return item from euros (R25)', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/manage/return-items', ['name' => 'Glass jar', 'amount' => '0.50'])
        ->assertRedirect('/manage/return-items');

    expect(ReturnItemModel::query()->firstOrFail()->amount_cents)->toBe(50);
});

it('shows the domain error when the amount is zero (R25)', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->from('/manage/return-items/create')
        ->post('/manage/return-items', ['name' => 'Glass jar', 'amount' => '0'])
        ->assertRedirect('/manage/return-items/create')
        ->assertSessionHasErrors('domain');

    expect(ReturnItemModel::query()->count())->toBe(0);
});

it('shows the edit form and answers 404 for an unknown return item', function () {
    $item = glassJar();
    $this->actingAs(accountWith(Role::Manager));

    $this->get("/manage/return-items/{$item->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->component('Manage/ReturnItems/Edit')->where('item.amount', '0.50'));
    $this->get('/manage/return-items/'.fake()->uuid().'/edit')->assertNotFound();
});

it('updates and deactivates a return item (R25)', function () {
    $item = glassJar();
    $this->actingAs(accountWith(Role::Manager));

    $this->put("/manage/return-items/{$item->id}", ['name' => 'Crate', 'amount' => '3'])
        ->assertRedirect('/manage/return-items');
    expect($item->refresh()->name)->toBe('Crate')->and($item->amount_cents)->toBe(300);

    $this->delete("/manage/return-items/{$item->id}")->assertRedirect('/manage/return-items');
    expect($item->refresh()->active)->toBeFalse();
});

it('answers 404 to a return item id that is not a UUID', function (string $method, string $uri) {
    $this->actingAs(accountWith(Role::Manager))->{$method}($uri, ['name' => 'Jar', 'amount' => '1'])->assertNotFound();
})->with([
    ['get', '/manage/return-items/not-a-uuid/edit'],
    ['put', '/manage/return-items/not-a-uuid'],
    ['delete', '/manage/return-items/not-a-uuid'],
]);
