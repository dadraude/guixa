<?php

use Guixa\Shared\Domain\Role;
use Guixa\Stock\Infrastructure\Persistence\ReturnItemModel;
use Inertia\Testing\AssertableInertia as Assert;

function glassJar(array $overrides = []): ReturnItemModel
{
    return ReturnItemModel::query()->create(['name' => 'Glass jar', 'amount_cents' => 50, 'active' => true, ...$overrides]);
}

it('keeps the return item pages to the manager and Catalogue', function (string $method, string $uri) {
    $item = glassJar();
    $uri = str_replace('{id}', $item->id, $uri);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->{$method}($uri)->assertForbidden();
})->with([
    ['get', '/catalogue/return-items'],
    ['get', '/catalogue/return-items/create'],
    ['post', '/catalogue/return-items'],
    ['get', '/catalogue/return-items/{id}/edit'],
    ['put', '/catalogue/return-items/{id}'],
    ['delete', '/catalogue/return-items/{id}'],
    ['put', '/catalogue/return-items/{id}/reactivation'],
]);

it('refuses Finance and Receiver the return item pages and lets Catalogue edit the catalogue (R5, S6)', function () {
    $item = glassJar();

    $this->actingAs(accountWith(Role::Finance))->get('/catalogue/return-items')->assertForbidden();
    $this->actingAs(accountWith(Role::Receiver))->put("/catalogue/return-items/{$item->id}", ['name' => 'Crate', 'amount' => '3'])->assertForbidden();

    $catalogue = accountWith(Role::Catalogue);
    $this->actingAs($catalogue)->get('/catalogue/return-items')->assertInertia(fn (Assert $page) => $page->component('Manage/ReturnItems/Index')->has('items', 1));
    $this->actingAs($catalogue)->post('/catalogue/return-items', ['name' => 'Crate', 'amount' => '3'])->assertRedirect('/catalogue/return-items');
    $this->actingAs($catalogue)->put("/catalogue/return-items/{$item->id}", ['name' => 'Big jar', 'amount' => '1'])->assertRedirect('/catalogue/return-items');
    $this->actingAs($catalogue)->delete("/catalogue/return-items/{$item->id}")->assertRedirect('/catalogue/return-items');
    expect($item->refresh()->active)->toBeFalse();
});

it('lists the return items with their amount as a decimal string (R25)', function () {
    glassJar();

    $this->actingAs(accountWith(Role::Manager))->get('/catalogue/return-items')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/ReturnItems/Index')
            ->has('items', 1)
            ->where('items.0.name', 'Glass jar')
            ->where('items.0.amount', '0.50')
            ->where('items.0.active', true));
});

it('creates a return item from euros (R25)', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->post('/catalogue/return-items', ['name' => 'Glass jar', 'amount' => '0.50'])
        ->assertRedirect('/catalogue/return-items');

    expect(ReturnItemModel::query()->firstOrFail()->amount_cents)->toBe(50);
});

it('shows the domain error when the amount is zero (R25)', function () {
    $this->actingAs(accountWith(Role::Manager))
        ->from('/catalogue/return-items/create')
        ->post('/catalogue/return-items', ['name' => 'Glass jar', 'amount' => '0'])
        ->assertRedirect('/catalogue/return-items/create')
        ->assertSessionHasErrors('domain');

    expect(ReturnItemModel::query()->count())->toBe(0);
});

it('shows the edit form and answers 404 for an unknown return item', function () {
    $item = glassJar();
    $this->actingAs(accountWith(Role::Manager));

    $this->get("/catalogue/return-items/{$item->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->component('Manage/ReturnItems/Edit')->where('item.amount', '0.50'));
    $this->get('/catalogue/return-items/'.fake()->uuid().'/edit')->assertNotFound();
});

it('updates and deactivates a return item (R25)', function () {
    $item = glassJar();
    $this->actingAs(accountWith(Role::Manager));

    $this->put("/catalogue/return-items/{$item->id}", ['name' => 'Crate', 'amount' => '3'])
        ->assertRedirect('/catalogue/return-items');
    expect($item->refresh()->name)->toBe('Crate')->and($item->amount_cents)->toBe(300);

    $this->delete("/catalogue/return-items/{$item->id}")->assertRedirect('/catalogue/return-items');
    expect($item->refresh()->active)->toBeFalse();
});

it('reactivates a return item (R25)', function () {
    $item = glassJar(['active' => false]);

    $this->actingAs(accountWith(Role::Manager))
        ->put("/catalogue/return-items/{$item->id}/reactivation")
        ->assertRedirect('/catalogue/return-items');

    expect($item->refresh()->active)->toBeTrue();
});

it('answers 404 to a return item id that is not a UUID', function (string $method, string $uri) {
    $this->actingAs(accountWith(Role::Manager))->{$method}($uri, ['name' => 'Jar', 'amount' => '1'])->assertNotFound();
})->with([
    ['get', '/catalogue/return-items/not-a-uuid/edit'],
    ['put', '/catalogue/return-items/not-a-uuid'],
    ['delete', '/catalogue/return-items/not-a-uuid'],
    ['put', '/catalogue/return-items/not-a-uuid/reactivation'],
]);
