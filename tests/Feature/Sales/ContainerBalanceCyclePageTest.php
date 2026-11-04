<?php

use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('reads each household balance from Sales baskets on the Manage cycle page, negative included (S92, R18a)', function () {
    [$cycleId, , $loaf] = cycleWithBaker();
    $manager = accountWith(Role::Manager);
    $producer = ProducerModel::query()->create(['name' => 'Honey maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $honey = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $honey, 'producer_id' => $producer->id, 'stock' => true, 'name' => 'Honey',
        'unit' => 'jar', 'price_cents' => 700, 'variable' => false, 'active' => true, 'containers_per_unit' => '1']);
    DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'stock_product_id' => $honey,
        'type' => 'entry', 'quantity' => '20', 'occurred_at' => now(), 'recorded_by' => $manager->id]);
    $jar = app(CreateReturnItem::class)(Actor::manager($manager->id), 'Glass jar', 50);
    $households = [];
    foreach (['A', 'B'] as $name) {
        $id = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $id, 'name' => "Household {$name}", 'created_at' => now(), 'updated_at' => now()]);
        $households[$name] = $id;
        $this->actingAs(memberOf($id, "Member {$name}"))->put("/cycles/{$cycleId}/order/lines/{$loaf}", ['quantity' => '1'])->assertRedirect();
    }
    $this->actingAs($manager);
    $validated = function (string $household, array $stock, ?int $returns = null) use ($honey, $jar): void {
        $this->post('/finance/baskets', ['household_id' => $household])->assertRedirect();
        $id = (string) DB::table('baskets')->orderByDesc('id')->value('id');
        foreach ($stock as $quantity) {
            $this->post("/finance/baskets/{$id}/stock-lines", ['stock_product_id' => $honey, 'stock_quantity' => (string) $quantity])->assertRedirect();
        }
        if ($returns !== null) {
            $this->post("/finance/baskets/{$id}/return-lines", ['return_item_id' => $jar, 'return_quantity' => (string) $returns])->assertRedirect();
        }
        $this->post("/finance/baskets/{$id}/validation")->assertRedirect();
    };
    $validated($households['A'], [3]);
    $validated($households['A'], [], 5);
    $validated($households['B'], [4]);
    $draft = $this->post('/finance/baskets', ['household_id' => $households['B']]);
    $draft->assertRedirect();

    $this->get("/manage/cycles/{$cycleId}")->assertInertia(fn (Assert $page) => $page
        ->where('containers.households.0.householdName', 'Household A')
        ->where('containers.households.0.balance', -2)
        ->where('containers.households.1.householdName', 'Household B')
        ->where('containers.households.1.balance', 4));
});
