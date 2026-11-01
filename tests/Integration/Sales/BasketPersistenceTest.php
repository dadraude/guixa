<?php

namespace Tests\Integration\Sales;

use App\Models\User;
use Guixa\Catalog\Application\UpdateStockProduct;
use Guixa\Catalog\Domain\Error\ProductHasStockUse;
use Guixa\Catalog\Infrastructure\Persistence\ProducerModel;
use Guixa\Sales\Application\AddReturnToBasket;
use Guixa\Sales\Application\AddStockToBasket;
use Guixa\Sales\Application\CancelDraftBasket;
use Guixa\Sales\Application\CreateStockBasket;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\RemoveReturnFromBasket;
use Guixa\Sales\Application\RemoveStockFromBasket;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Sales\Application\WalletLedger;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\Error\BasketAlreadyValidated;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Sales\Domain\Error\InvalidReturnQuantity;
use Guixa\Sales\Domain\Error\StockProductUnavailable;
use Guixa\Sales\Infrastructure\Persistence\BasketLineModel;
use Guixa\Sales\Infrastructure\Persistence\EloquentBasketRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Application\CreateReturnItem;
use Guixa\Stock\Application\DeactivateReturnItem;
use Guixa\Stock\Application\RecordStockAdjustment;
use Guixa\Stock\Application\RecordStockEntry;
use Guixa\Stock\Application\UpdateReturnItem;
use Guixa\Stock\Domain\Error\NotEnoughStock;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Wallet\Application\GetWalletStatement;
use Guixa\Wallet\Application\RecordTopUp;
use Guixa\Wallet\Domain\Error\BasketAlreadyCharged;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BasketPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $priceCents = 1200, string $quantity = '5', bool $variable = false, ?string $containersPerUnit = null): array
    {
        $user = User::factory()->create();
        $actor = Actor::manager($user->id);
        $householdId = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $householdId, 'name' => 'Household K', 'created_at' => now(), 'updated_at' => now()]);
        $producer = ProducerModel::query()->create(['name' => 'Oil maker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
        $productId = (string) Str::uuid7();
        DB::table('products')->insert(['id' => $productId, 'producer_id' => $producer->id,
            'stock' => true, 'name' => 'Oil', 'unit' => 'bottle', 'price_cents' => $priceCents,
            'variable' => $variable, 'containers_per_unit' => $containersPerUnit, 'active' => true]);
        app(RecordStockEntry::class)($actor, $productId, $quantity, null);

        return [$actor, $householdId, $productId];
    }

    #[Test]
    public function a_return_only_basket_reloads_its_snapshot_and_records_one_credit(): void
    {
        [$actor, $householdId] = $this->fixture();
        $returnItemId = app(CreateReturnItem::class)($actor, 'Glass jar', 50);
        $id = app(CreateStockBasket::class)($actor, $householdId);

        app(AddReturnToBasket::class)($actor, $id, $returnItemId, '1');
        $draft = app(EloquentBasketRepository::class)->get($id);
        $this->assertSame('return', $draft->activeLines()[0]->kind);
        $this->assertSame($returnItemId, $draft->activeLines()[0]->returnItemId);
        $this->assertSame(-50, $draft->totalCents());

        app(ValidateBasket::class)($actor, $id);
        $validated = app(EloquentBasketRepository::class)->get($id);
        $this->assertSame(-50, $validated->validatedTotalCents);
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
        $this->assertSame('credit', DB::table('wallet_movements')->where('basket_id', $id)->value('type'));
        $this->assertSame(50, (int) DB::table('wallet_movements')->where('basket_id', $id)->value('amount_cents'));
        $this->assertSame(0, DB::table('stock_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function mixed_goods_and_four_returns_charge_exactly_eight_euros_once(): void
    {
        [$actor, $householdId, $productId] = $this->fixture(priceCents: 1000);
        $returnItemId = app(CreateReturnItem::class)($actor, 'Glass jar', 50);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(AddReturnToBasket::class)($actor, $id, $returnItemId, '4');

        app(ValidateBasket::class)($actor, $id);
        $this->assertSame(800, app(EloquentBasketRepository::class)->get($id)->validatedTotalCents);
        $this->assertSame(-800, (int) DB::table('wallet_movements')->where('basket_id', $id)->value('amount_cents'));
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
        $this->assertSame(1, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertThrows(fn () => app(ValidateBasket::class)($actor, $id), BasketAlreadyValidated::class);
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function mixed_real_contents_can_validate_at_zero_and_collect_without_a_wallet_movement(): void
    {
        [$actor, $householdId, $productId] = $this->fixture(priceCents: 50);
        $returnItemId = app(CreateReturnItem::class)($actor, 'Glass jar', 50);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(AddReturnToBasket::class)($actor, $id, $returnItemId, '1');

        app(ValidateBasket::class)($actor, $id);
        app(MarkBasketCollected::class)($actor, $id);
        $this->assertSame('collected', app(EloquentBasketRepository::class)->get($id)->state);
        $this->assertSame(0, app(EloquentBasketRepository::class)->get($id)->validatedTotalCents);
        $this->assertSame(0, DB::table('wallet_movements')->where('basket_id', $id)->count());
        $this->assertSame(1, DB::table('stock_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function a_removed_return_reloads_as_history_and_readding_takes_the_new_catalogue_snapshot(): void
    {
        [$actor, $householdId] = $this->fixture();
        $returnItemId = app(CreateReturnItem::class)($actor, 'Glass jar', 50);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddReturnToBasket::class)($actor, $id, $returnItemId, '1');
        app(UpdateReturnItem::class)($actor, $returnItemId, 'Changed jar', 75);
        app(RemoveReturnFromBasket::class)($actor, $id, $returnItemId);
        app(AddReturnToBasket::class)($actor, $id, $returnItemId, '1');

        $reloaded = app(EloquentBasketRepository::class)->get($id);
        $this->assertCount(2, $reloaded->lines);
        $this->assertSame('Glass jar', $reloaded->lines[0]->name);
        $this->assertNotNull($reloaded->lines[0]->removedAt);
        $this->assertSame('Changed jar', $reloaded->activeLines()[0]->name);
        $this->assertSame(-75, $reloaded->totalCents());
    }

    #[Test]
    public function an_inactive_return_can_be_increased_and_readded_with_the_correct_snapshots_and_credit(): void
    {
        [$actor, $householdId] = $this->fixture();
        $item = app(CreateReturnItem::class)($actor, 'Glass jar', 50);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddReturnToBasket::class)($actor, $id, $item, '1');
        app(UpdateReturnItem::class)($actor, $item, 'Changed jar', 75);
        app(DeactivateReturnItem::class)($actor, $item);
        app(AddReturnToBasket::class)($actor, $id, $item, '1');

        $draft = app(EloquentBasketRepository::class)->get($id);
        $this->assertSame('Glass jar', $draft->activeLines()[0]->name);
        $this->assertSame(50, $draft->activeLines()[0]->priceCents);
        $this->assertSame('2.000', $draft->activeLines()[0]->quantity->toString());

        app(RemoveReturnFromBasket::class)($actor, $id, $item);
        app(AddReturnToBasket::class)($actor, $id, $item, '1');
        $reloaded = app(EloquentBasketRepository::class)->get($id);
        $this->assertCount(2, $reloaded->lines);
        $this->assertNotNull($reloaded->lines[0]->removedAt);
        $this->assertSame('Changed jar', $reloaded->activeLines()[0]->name);
        $this->assertSame(75, $reloaded->activeLines()[0]->priceCents);
        app(ValidateBasket::class)($actor, $id);
        $this->assertSame(-75, app(EloquentBasketRepository::class)->get($id)->validatedTotalCents);
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
        $this->assertSame('credit', DB::table('wallet_movements')->where('basket_id', $id)->value('type'));
        $this->assertSame(75, (int) DB::table('wallet_movements')->where('basket_id', $id)->value('amount_cents'));
    }

    #[Test]
    public function an_over_limit_return_increment_preserves_the_persisted_draft_and_ledgers(): void
    {
        [$actor, $householdId] = $this->fixture();
        $item = app(CreateReturnItem::class)($actor, 'Glass jar', 50);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        $this->assertThrows(fn () => app(AddReturnToBasket::class)($actor, $id, $item, '10000000'), InvalidReturnQuantity::class);
        $this->assertSame(0, DB::table('basket_lines')->where('basket_id', $id)->count());

        app(AddReturnToBasket::class)($actor, $id, $item, '9999999');
        $before = app(EloquentBasketRepository::class)->get($id)->activeLines()[0];
        $this->assertThrows(fn () => app(AddReturnToBasket::class)($actor, $id, $item, '1'), InvalidReturnQuantity::class);
        $after = app(EloquentBasketRepository::class)->get($id);
        $this->assertSame('draft', $after->state);
        $this->assertCount(1, $after->lines);
        $this->assertSame($before->quantity->toString(), $after->activeLines()[0]->quantity->toString());
        $this->assertSame($before->name, $after->activeLines()[0]->name);
        $this->assertSame($before->priceCents, $after->activeLines()[0]->priceCents);
        $this->assertSame(0, DB::table('wallet_movements')->where('basket_id', $id)->count());
        $this->assertSame(0, DB::table('stock_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function mixed_validation_rolls_back_stock_credit_and_state_after_wallet_failure_then_retries_once(): void
    {
        [$actor, $householdId, $productId] = $this->fixture(priceCents: 25);
        $returnItemId = app(CreateReturnItem::class)($actor, 'Glass jar', 50);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(AddReturnToBasket::class)($actor, $id, $returnItemId, '1');
        $real = app(WalletLedger::class);
        app()->instance(WalletLedger::class, new class($real) implements WalletLedger
        {
            public function __construct(private readonly WalletLedger $inner) {}

            public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void
            {
                $this->inner->charge($actor, $householdId, $basketId, $totalCents);
                throw new \RuntimeException('Injected wallet failure');
            }

            public function balance(Actor $actor, string $householdId): int
            {
                return $this->inner->balance($actor, $householdId);
            }

            public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void
            {
                $this->inner->correct($actor, $householdId, $basketId, $amountCents, $reason, $correctionId);
            }
        });

        $this->assertThrows(fn () => app(ValidateBasket::class)($actor, $id), \RuntimeException::class);
        $this->assertSame('draft', DB::table('baskets')->where('id', $id)->value('state'));
        $this->assertSame(0, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertSame(0, DB::table('wallet_movements')->where('basket_id', $id)->count());

        app()->instance(WalletLedger::class, $real);
        app(ValidateBasket::class)($actor, $id);
        $this->assertSame(-25, app(EloquentBasketRepository::class)->get($id)->validatedTotalCents);
        $this->assertSame(1, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
        $this->assertThrows(fn () => app(ValidateBasket::class)($actor, $id), BasketAlreadyValidated::class);
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function active_stock_remains_available_to_a_basket_after_its_producer_is_deactivated(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $producerId = DB::table('products')->where('id', $productId)->value('producer_id');
        DB::table('producers')->where('id', $producerId)->update(['active' => false]);

        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(ValidateBasket::class)($actor, $id);

        $this->assertSame('validated', DB::table('baskets')->where('id', $id)->value('state'));
        $this->assertSame(1, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function a_cycleless_basket_keeps_its_snapshot_sale_charge_and_first_collection(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        $this->assertNull(DB::table('baskets')->where('id', $id)->value('validated_at'));
        $this->assertSame(0, DB::table('wallet_movements')->count());

        app(ValidateBasket::class)($actor, $id);
        $this->assertSame(1, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
        $this->assertSame(-1200, (int) DB::table('wallet_movements')->where('basket_id', $id)->value('amount_cents'));
        $this->assertSame('validated', DB::table('baskets')->where('id', $id)->value('state'));
        $this->assertSame('Oil', DB::table('basket_lines')->where('basket_id', $id)->value('name'));

        $this->assertThrows(fn () => app(ValidateBasket::class)($actor, $id), BasketAlreadyValidated::class);
        app(MarkBasketCollected::class)($actor, $id);
        $first = DB::table('baskets')->where('id', $id)->value('collected_at');
        app(MarkBasketCollected::class)($actor, $id);
        $this->assertSame($first, DB::table('baskets')->where('id', $id)->value('collected_at'));
        $this->assertSame(1, DB::table('basket_collection_events')->where('basket_id', $id)->count());
        $this->assertSame(1, DB::table('wallet_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function a_variable_stock_line_reloads_its_exact_non_null_container_snapshot(): void
    {
        [$actor, $householdId, $productId] = $this->fixture(650, '5', true, '2.125');
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1.500');

        $line = app(EloquentBasketRepository::class)->get($id)->activeLines()[0];
        $this->assertTrue($line->variable);
        $this->assertSame('2.125', $line->containersPerUnit);
        $this->assertSame('1.500', $line->quantity->toString());
        $this->assertSame(975, $line->amountCents());
        $this->assertSame('2.125', (string) DB::table('basket_lines')->where('basket_id', $id)->value('containers_per_unit'));
        $storedLine = BasketLineModel::query()->where('basket_id', $id)->firstOrFail();
        $this->assertSame('2.125', $storedLine->getRawOriginal('containers_per_unit'));
    }

    #[Test]
    public function a_removed_line_remains_history_and_readding_creates_a_new_snapshot(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(RemoveStockFromBasket::class)($actor, $id, $productId);
        $this->assertTrue(app(EloquentBasketRepository::class)->hasProductHistory($productId));
        $this->assertSame(0, count(app(EloquentBasketRepository::class)->get($id)->activeLines()));
        DB::table('products')->where('id', $productId)->update(['price_cents' => 1500]);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        $this->assertSame(2, DB::table('basket_lines')->where('basket_id', $id)->count());
        $this->assertSame([1200, 1500], DB::table('basket_lines')->where('basket_id', $id)->orderBy('created_at')->orderBy('id')->pluck('price_cents')->sort()->values()->all());
    }

    #[Test]
    public function a_cancelled_populated_draft_reloads_with_no_current_contents_or_total_but_keeps_history(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(CancelDraftBasket::class)($actor, $id);

        $repository = app(EloquentBasketRepository::class);
        $reloaded = $repository->get($id);
        $this->assertSame('cancelled', $reloaded->state);
        $this->assertCount(1, $reloaded->lines);
        $this->assertSame([], $reloaded->activeLines());
        $this->assertSame(0, $reloaded->totalCents());
        $this->assertTrue($repository->hasProductHistory($productId));
        $this->assertSame(0, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertSame(0, DB::table('wallet_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function competing_drafts_recheck_stock_and_leave_the_loser_draft_without_a_charge(): void
    {
        [$actor, $householdId, $productId] = $this->fixture(quantity: '5');
        $first = app(CreateStockBasket::class)($actor, $householdId);
        $second = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $first, $productId, '3');
        app(AddStockToBasket::class)($actor, $second, $productId, '3');

        app(ValidateBasket::class)($actor, $first);
        $this->assertThrows(fn () => app(ValidateBasket::class)($actor, $second), NotEnoughStock::class);

        $this->assertSame('draft', DB::table('baskets')->where('id', $second)->value('state'));
        $this->assertSame(0, DB::table('wallet_movements')->where('basket_id', $second)->count());
        $this->assertSame(0, DB::table('stock_movements')->where('basket_id', $second)->count());
        $this->assertSame('2.000', app(StockMovementRepository::class)->available($productId)->toString());
    }

    #[Test]
    public function a_wallet_failure_after_the_charge_rolls_back_stock_money_and_validation(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        $real = app(WalletLedger::class);
        app()->instance(WalletLedger::class, new class($real) implements WalletLedger
        {
            public function __construct(private readonly WalletLedger $inner) {}

            public function charge(Actor $actor, string $householdId, string $basketId, int $totalCents): void
            {
                $this->inner->charge($actor, $householdId, $basketId, $totalCents);
                throw new \RuntimeException('Injected wallet failure');
            }

            public function balance(Actor $actor, string $householdId): int
            {
                return $this->inner->balance($actor, $householdId);
            }

            public function correct(Actor $actor, string $householdId, string $basketId, int $amountCents, string $reason, string $correctionId): void
            {
                $this->inner->correct($actor, $householdId, $basketId, $amountCents, $reason, $correctionId);
            }
        });

        $this->assertThrows(fn () => app(ValidateBasket::class)($actor, $id), \RuntimeException::class);
        $this->assertSame('draft', DB::table('baskets')->where('id', $id)->value('state'));
        $this->assertSame(0, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertSame(0, DB::table('wallet_movements')->where('basket_id', $id)->count());
    }

    #[Test]
    public function a_validated_snapshot_cannot_be_rewritten_through_the_repository(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(ValidateBasket::class)($actor, $id);
        $repository = app(EloquentBasketRepository::class);
        $basket = $repository->get($id);
        $line = $basket->activeLines()[0];
        $changed = new BasketLine($line->id, $line->basketId, $line->productId, 'Changed name',
            $line->unit, $line->priceCents, $line->variable, $line->containersPerUnit,
            $line->quantity, $line->createdAt, $line->removedAt);
        $rewritten = new Basket($basket->id, $basket->householdId, $basket->householdName, $basket->state,
            $basket->createdBy, $basket->createdAt, [$changed], $basket->validatedBy,
            $basket->validatedAt, $basket->validatedTotalCents);

        $this->assertThrows(fn () => $repository->save($rewritten), BasketNotEditable::class);
        $this->assertSame('Oil', DB::table('basket_lines')->where('basket_id', $id)->value('name'));
    }

    #[Test]
    public function basket_sources_allow_separate_purchases_but_reject_a_second_sale_or_charge_for_one_basket(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $ids = [];
        foreach ([1, 2] as $purchase) {
            $id = app(CreateStockBasket::class)($actor, $householdId);
            app(AddStockToBasket::class)($actor, $id, $productId, '1');
            app(ValidateBasket::class)($actor, $id);
            $ids[] = $id;
        }
        $this->assertSame(2, DB::table('stock_movements')->whereNotNull('basket_id')->count());
        $this->assertSame(2, DB::table('wallet_movements')->whereNotNull('basket_id')->count());
        $this->assertSame(-2400, app(MovementRepository::class)->balanceOf($householdId)->cents);
        $statement = app(GetWalletStatement::class)($actor, $householdId);
        $this->assertEqualsCanonicalizing($ids, array_map(fn ($movement) => $movement->basketId, $statement->movements));

        $wallet = app(MovementRepository::class);
        $duplicateCharge = Movement::basketCharge($wallet->nextId(), $householdId, Money::ofCents(1200),
            $ids[0], new \DateTimeImmutable('now'), $actor->accountId);
        $this->assertThrows(fn () => $wallet->add($duplicateCharge), BasketAlreadyCharged::class);
        $stock = app(StockMovementRepository::class);
        $duplicateSale = StockMovement::basketSale($stock->nextId(), $productId, Quantity::fromString('1'),
            new \DateTimeImmutable('now'), $actor->accountId, $ids[0]);
        $this->assertThrows(fn () => DB::transaction(fn () => $stock->add($duplicateSale)), UniqueConstraintViolationException::class);
    }

    #[Test]
    public function removed_and_cancelled_stock_drafts_block_catalog_unit_and_variable_changes(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $producerId = DB::table('products')->where('id', $productId)->value('producer_id');
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(RemoveStockFromBasket::class)($actor, $id, $productId);
        app(CancelDraftBasket::class)($actor, $id);
        $this->assertSame('cancelled', DB::table('baskets')->where('id', $id)->value('state'));
        $this->assertSame(0, DB::table('basket_lines')->where('basket_id', $id)->whereNull('removed_at')->count());
        $this->assertThrows(fn () => app(UpdateStockProduct::class)($actor, $producerId, $productId,
            'Oil', 'litre', 1200, true, null), ProductHasStockUse::class);
        app(UpdateStockProduct::class)($actor, $producerId, $productId, 'New oil', 'bottle', 1300, false, null);
        $this->assertSame('New oil', DB::table('products')->where('id', $productId)->value('name'));
    }

    #[Test]
    public function an_existing_line_validates_after_product_deactivation_but_cannot_be_increased(): void
    {
        [$actor, $householdId, $productId] = $this->fixture();
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        DB::table('products')->where('id', $productId)->update(['active' => false]);
        $this->assertThrows(fn () => app(AddStockToBasket::class)($actor, $id, $productId, '1'), StockProductUnavailable::class);
        app(ValidateBasket::class)($actor, $id);
        $this->assertSame('validated', DB::table('baskets')->where('id', $id)->value('state'));
        $this->assertSame(-1200, app(MovementRepository::class)->balanceOf($householdId)->cents);
    }

    #[Test]
    public function two_fractional_lines_round_half_up_separately_to_the_validated_total(): void
    {
        [$actor, $householdId, $first] = $this->fixture(priceCents: 100, quantity: '1');
        DB::table('products')->where('id', $first)->update(['variable' => true]);
        $second = (string) Str::uuid7();
        DB::table('products')->insert(['id' => $second,
            'producer_id' => DB::table('products')->where('id', $first)->value('producer_id'),
            'stock' => true, 'name' => 'Rice', 'unit' => 'kg', 'price_cents' => 100,
            'variable' => true, 'active' => true]);
        app(RecordStockEntry::class)($actor, $second, '1', null);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $first, '0.125');
        app(AddStockToBasket::class)($actor, $id, $second, '0.125');
        app(ValidateBasket::class)($actor, $id);

        $this->assertSame(26, (int) DB::table('baskets')->where('id', $id)->value('validated_total_cents'));
        $this->assertSame(-26, (int) DB::table('wallet_movements')->where('basket_id', $id)->value('amount_cents'));
        $this->assertSame(-26, app(MovementRepository::class)->balanceOf($householdId)->cents);
        $this->assertSame('0.875', app(StockMovementRepository::class)->available($first)->toString());
        $this->assertSame('0.875', app(StockMovementRepository::class)->available($second)->toString());
    }

    #[Test]
    public function a_global_wallet_balance_may_be_negative_after_a_basket_charge(): void
    {
        [$actor, $householdId, $productId] = $this->fixture(priceCents: 3000);
        app(RecordTopUp::class)($actor, $householdId, 500, null);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productId, '1');
        app(ValidateBasket::class)($actor, $id);

        $this->assertSame(-2500, app(MovementRepository::class)->balanceOf($householdId)->cents);
        $this->assertSame(2, DB::table('wallet_movements')->where('household_id', $householdId)->count());
    }

    #[Test]
    public function a_failure_on_the_second_stock_sale_rolls_back_the_first_sale_and_basket(): void
    {
        [$actor, $householdId, $productA] = $this->fixture(quantity: '5');
        $productB = (string) Str::uuid7();
        DB::table('products')->insert(['id' => $productB,
            'producer_id' => DB::table('products')->where('id', $productA)->value('producer_id'),
            'stock' => true, 'name' => 'Honey', 'unit' => 'jar', 'price_cents' => 600,
            'variable' => false, 'active' => true]);
        app(RecordStockEntry::class)($actor, $productB, '5', null);
        $id = app(CreateStockBasket::class)($actor, $householdId);
        app(AddStockToBasket::class)($actor, $id, $productA, '2');
        app(AddStockToBasket::class)($actor, $id, $productB, '2');
        $ids = [$productA, $productB];
        sort($ids);
        app(RecordStockAdjustment::class)($actor, $ids[1], '-4', 'sold elsewhere', null);

        $this->assertThrows(fn () => app(ValidateBasket::class)($actor, $id), NotEnoughStock::class);
        $this->assertSame('draft', DB::table('baskets')->where('id', $id)->value('state'));
        $this->assertSame(0, DB::table('stock_movements')->where('basket_id', $id)->count());
        $this->assertSame(0, DB::table('wallet_movements')->where('basket_id', $id)->count());
    }
}
