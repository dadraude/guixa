<?php

use App\Models\User;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Catalog\Infrastructure\Persistence\ProductModel;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function historyProducer(string $name): ProducerModel
{
    return ProducerModel::query()->create(['name' => $name, 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
}

function historyProduct(ProducerModel $producer, string $name, bool $stock = true, bool $active = true): ProductModel
{
    return ProductModel::query()->create([
        'producer_id' => $producer->id, 'name' => $name, 'unit' => 'jar', 'price_cents' => 650,
        'variable' => false, 'active' => $active, 'stock' => $stock,
    ]);
}

function historyProducerAccount(ProducerModel $producer): User
{
    $user = User::factory()->create(['producer_id' => $producer->id]);
    DB::table('account_roles')->insert(['user_id' => $user->id, 'role' => Role::Producer->value]);

    return $user;
}

/** S93: entry 10, adjustment -1, sale 3, correction +1; returns the basket id. */
function recordHoneyHistory(ProductModel $honey, User $author, int $extraEntries = 0): string
{
    $household = (string) Str::uuid7();
    $basket = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Household A', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('baskets')->insert([
        'id' => $basket, 'household_id' => $household, 'household_name' => 'Household A',
        'kind' => 'stock', 'state' => 'draft', 'created_by' => $author->id, 'created_at' => now(),
    ]);
    $correction = (string) Str::uuid7();
    DB::table('basket_adjustments')->insert(['id' => $correction, 'basket_id' => $basket, 'amount_cents' => 650,
        'reason' => 'wrong count', 'recorded_by' => $author->id, 'occurred_at' => now()]);
    $row = fn (int $minute, string $type, string $quantity, ?string $note, array $extra = []) => array_merge([
        'id' => (string) Str::uuid7(), 'product_id' => $honey->id, 'type' => $type, 'quantity' => $quantity,
        'occurred_at' => "2026-10-20 09:0{$minute}:00+00", 'recorded_by' => $author->id, 'note' => $note,
    ], $extra);
    foreach ([
        $row(0, 'entry', '10.000', 'bought from X'),
        $row(1, 'adjustment', '-1.000', 'broken jar'),
        $row(2, 'sale', '-3.000', null, ['basket_id' => $basket]),
        $row(3, 'adjustment', '1.000', 'wrong count', ['basket_id' => $basket, 'correction_id' => $correction]),
    ] as $movement) {
        DB::table('stock_movements')->insert($movement);
    }
    for ($i = 0; $i < $extraEntries; $i++) {
        DB::table('stock_movements')->insert($row(4, 'entry', '1.000', "extra {$i}"));
    }

    return $basket;
}

beforeEach(function () {
    $this->beekeeper = historyProducer('Beekeeper');
    $this->oilMaker = historyProducer('Oil maker');
    $this->honey = historyProduct($this->beekeeper, 'Honey');
    $this->oil = historyProduct($this->oilMaker, 'Oil');
    $this->author = User::factory()->create(['name' => 'Maria Manager']);
    $this->basket = recordHoneyHistory($this->honey, $this->author);
});

it('shows the Manager the history below the level, with baskets on sale and correction (S93)', function () {
    $this->actingAs(accountWith(Role::Manager))->get("/manage/stock/{$this->honey->id}/movements")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Stock/Movements')
            ->where('history.total', 4)
            ->where('history.lines.0.type', 'correction')
            ->where('history.lines.0.basketId', $this->basket)
            ->where('history.lines.1.type', 'sale')
            ->where('history.lines.2.authorName', 'Maria Manager')
            ->where('history.lines', fn ($lines) => $lines->pluck('balanceAfter')->all() === ['7.000', '6.000', '9.000', '10.000'])
            ->where('history.lines.3.occurredAt', '2026-10-20 11:00')
            ->where('history.prevUrl', null)
            ->where('history.nextUrl', null));
});

it('lets Finance read the list and the same history but record nothing (S93, R36c)', function () {
    $finance = accountWith(Role::Finance);

    $this->actingAs($finance)->get('/finance/stock')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Finance/Stock/Index')->has('products', 2));
    $this->get("/finance/stock/{$this->honey->id}/movements")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Stock/Movements')
            ->where('product.name', 'Honey')
            ->where('history.lines.0.basketId', $this->basket)
            ->where('history.total', 4));
    $this->post("/manage/stock/{$this->honey->id}/entries", ['quantity' => '1'])->assertForbidden();
});

it('keeps the history readable after the product is deactivated, for Manager and Finance (S93)', function () {
    $this->honey->update(['active' => false]);

    $this->actingAs(accountWith(Role::Finance))->get("/finance/stock/{$this->honey->id}/movements")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('history.total', 4)->where('product.name', 'Honey')->where('product.available', '7.000'));
    $this->actingAs(accountWith(Role::Manager))->get("/finance/stock/{$this->honey->id}/movements")->assertOk();
    $this->actingAs(historyProducerAccount($this->beekeeper))->get("/producer/stock/{$this->honey->id}/movements")
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('history.total', 4));
});

it('shows the producer the same lines without any basket anywhere in the payload (S93, R25b)', function () {
    $response = $this->actingAs(historyProducerAccount($this->beekeeper))->get("/producer/stock/{$this->honey->id}/movements")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Producer/Stock/Movements')
            ->where('history.lines', fn ($lines) => $lines->pluck('type')->all() === ['correction', 'sale', 'adjustment', 'entry'])
            ->where('history.lines', fn ($lines) => $lines->pluck('basketId')->all() === [null, null, null, null]));

    expect(json_encode($response->viewData('page')['props']))->not->toContain($this->basket)->not->toContain('Household A');
});

it('forbids the producer another producer\'s stock history and shows a weekly product as missing (S93, R25b)', function () {
    $weekly = historyProduct($this->beekeeper, 'Weekly honey', stock: false);
    $producer = historyProducerAccount($this->beekeeper);

    $this->actingAs($producer)->get("/producer/stock/{$this->oil->id}/movements")->assertForbidden();
    $this->get("/producer/stock/{$weekly->id}/movements")->assertNotFound();
    $this->get('/producer/stock/'.Str::uuid7().'/movements')->assertNotFound();
});

it('forbids members, producers and Finance outside their area (S93)', function () {
    $this->actingAs(accountWith(Role::Member))->get("/finance/stock/{$this->honey->id}/movements")->assertForbidden();
    $this->get('/finance/stock')->assertForbidden();
    $this->get("/producer/stock/{$this->honey->id}/movements")->assertForbidden();
    $this->actingAs(historyProducerAccount($this->beekeeper))->get("/finance/stock/{$this->honey->id}/movements")->assertForbidden();
    $this->actingAs(accountWith(Role::Finance))->get("/producer/stock/{$this->honey->id}/movements")->assertForbidden();
    $this->actingAs(accountWith(Role::Finance))->get("/manage/stock/{$this->honey->id}/movements")->assertForbidden();
});

it('pages the history 50 lines at a time with previous and next links (S93)', function () {
    recordHoneyHistory(historyProduct($this->beekeeper, 'Jam'), $this->author, 56);
    $jam = ProductModel::query()->where('name', 'Jam')->firstOrFail();

    $this->actingAs(accountWith(Role::Manager))->get("/finance/stock/{$jam->id}/movements")
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.lines', 50)->where('history.total', 60)->where('history.page', 1)
            ->where('history.prevUrl', null)->where('history.nextUrl', url("/finance/stock/{$jam->id}/movements?page=2")));
    $this->get("/finance/stock/{$jam->id}/movements?page=2")
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.lines', 10)->where('history.page', 2)->where('history.nextUrl', null)
            ->where('history.prevUrl', url("/finance/stock/{$jam->id}/movements?page=1")));
});

it('answers a page past the end with empty lines, a link back to the last page and no error (S93)', function () {
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->get("/finance/stock/{$this->honey->id}/movements?page=99999999999999999999")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('history.lines', 0)->where('history.total', 4)->where('history.nextUrl', null)
            ->where('history.prevUrl', url("/finance/stock/{$this->honey->id}/movements?page=1")));
    $this->get("/manage/stock/{$this->honey->id}/movements?page=3")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('history.lines', 0)->where('history.total', 4));
});
