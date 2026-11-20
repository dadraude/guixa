<?php

namespace Database\Seeders;

use App\Models\User;
use DateTimeImmutable;
use Guixa\Catalog\Application\CreateProducer;
use Guixa\Catalog\Application\CreateProduct;
use Guixa\Catalog\Application\CreateStockProduct;
use Guixa\Catalog\Application\DeactivateProducer;
use Guixa\Catalog\Application\DeactivateProduct;
use Guixa\Catalog\Application\DeactivateStockProduct;
use Guixa\Membership\Application\CreateHousehold;
use Guixa\Membership\Application\DeactivateAccount;
use Guixa\Membership\Application\DeactivateHousehold;
use Guixa\Membership\Application\GrantStaffRole;
use Guixa\Membership\Domain\Account;
use Guixa\Membership\Domain\AccountPasswords;
use Guixa\Membership\Domain\AccountRepository;
use Guixa\Membership\Domain\ValueObject\Email;
use Guixa\Ordering\Application\CloseProducerNow;
use Guixa\Ordering\Application\MarkProducerDistributed;
use Guixa\Ordering\Application\OpenCycle;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Ordering\Application\SetProductAvailability;
use Guixa\Ordering\Application\SetReceivedTotal;
use Guixa\Ordering\Application\ValidateReception;
use Guixa\Ordering\Domain\CooperativeSettings;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Sales\Application\FindWeeklyBaseBasket;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Application\ValidateBasket;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\ActorResolver;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Quantity;
use Guixa\Shared\Domain\Role;
use Guixa\Stock\Application\CreateReturnItem;
use Guixa\Stock\Application\DeactivateReturnItem;
use Guixa\Stock\Application\RecordStockAdjustment;
use Guixa\Stock\Application\RecordStockEntry;
use Guixa\Stock\Application\SetStockAvailable;
use Guixa\Wallet\Application\RecordTopUp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use LogicException;
use PragmaRX\Google2FA\Google2FA;

final class DemoSeeder extends Seeder
{
    private const string MANAGER_ID = '019a0000-0000-7000-8000-000000000001';

    private const string MANAGER_EMAIL = 'gestora@demo.guixa.test';

    private const string PASSWORD = 'password';

    private const string UNLINKED_NAME = 'Eva Sense Llar';

    private const string UNLINKED_EMAIL = 'eva@demo.guixa.test';

    private const string FORMER_HOUSEHOLD_NAME = 'Can Tancada';

    private const string FORMER_MEMBER_NAME = 'Jan Roca';

    private const string FORMER_MEMBER_EMAIL = 'jan@demo.guixa.test';

    private const string TWO_FACTOR_EMAIL = 'carla@demo.guixa.test';

    private const array HOUSEHOLDS = [
        ['Can Roca', 'Anna Roca', 'anna@demo.guixa.test', 10000],
        ['La Plaça', 'Biel Soler', 'biel@demo.guixa.test', 7500],
        ['Els Pins', 'Carla Pons', 'carla@demo.guixa.test', 5000],
        ['La Font', 'David Font', 'david@demo.guixa.test', 2500],
    ];

    /**
     * Staff roles granted to the households' accounts, by household index, so the role screen has holders. Receiver is granted
     * to nobody, the role-with-no-holder state; the first account is member and Finance at once (one collection group).
     */
    private const array STAFF_ROLES = [
        0 => [Role::Finance],
        1 => [Role::Catalogue],
    ];

    private const array PRODUCERS = [
        ['Hort de la Vall', 'hort@demo.guixa.test', [
            ['Pastanagues', 'kg', 240, true, null],
            ['Tomàquets', 'kg', 350, true, null],
        ]],
        ['Forn del Barri', 'forn@demo.guixa.test', [
            ['Pa de pagès', 'peça', 380, false, null],
            ['Croissants', 'peça', 180, false, null],
        ]],
        ['La Formatgeria', 'formatgeria@demo.guixa.test', [
            ['Formatge de cabra', 'kg', 1800, true, null],
            ['Iogurt natural', 'pot', 150, false, '1.000'],
        ]],
    ];

    private const string EMPTY_PRODUCER_EMAIL = 'sense-productes@demo.guixa.test';

    /** Stock history must exceed ListStockMovements::PER_PAGE (50) to span a second page. */
    private const int EXTRA_MOVEMENTS = 51;

    private const array QUANTITIES = [
        ['1.500', '1.000', '1', '2', '0.250', '4'],
        ['1.000', '2.000', '2', '0', '0.500', '2'],
        ['2.000', '0.750', '1', '4', '0.350', '6'],
        ['0.500', '1.250', '1', '2', '0.200', '2'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo data can only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            // This account is committed with the whole demo; reruns preserve any edits made while trying the app.
            if (User::query()->whereKey(self::MANAGER_ID)->exists()) {
                return;
            }

            $this->createDemo();
        });
    }

    private function createDemo(): void
    {
        $manager = $this->createAccount(Account::inviteManager(
            self::MANAGER_ID, 'Gestora Demo', Email::from(self::MANAGER_EMAIL),
        ));
        $members = $this->createHouseholds($manager);
        $this->grantStaffRoles($manager, $members);
        $this->createUnlinkedAccount($members[0]['householdId']);
        $catalog = $this->createCatalog($manager);
        $this->createStock($manager, $catalog['producers'][0]);
        $dates = $this->pickupDates();
        $cycles = [];

        foreach ($dates as $date) {
            $weekday = app(CooperativeSettings::class)->pickupWeekday();
            $cycleId = app(OpenCycle::class)(
                $manager, $date->format('Y-m-d'), $catalog['producers'],
                $weekday === null ? (int) $date->format('N') : null, $weekday === null,
            );
            $this->createOrders($cycleId, $members, $catalog['products']);
            $cycles[] = $cycleId;
        }

        $this->prepareReception($cycles[0], $catalog['producers']);
        $this->receiveProducts($manager, $cycles[0], $catalog['products']);
        foreach ($catalog['producers'] as $producerId) {
            app(ValidateReception::class)($manager, $cycles[0], $producerId);
            app(MarkProducerDistributed::class)($manager, $cycles[0], $producerId);
        }
        $this->collectFirstBasket($manager, $cycles[0], $members[0]['householdId']);
        $this->validateBasketAwaitingCollection($manager, $cycles[0], $members[1]['householdId']);

        $this->prepareReception($cycles[1], $catalog['producers']);
        // The next cycle shows every reception state the dashboard lists: the first producer is validated and balanced
        // (awaiting distribution), the third has default totals recorded but is unvalidated, the second was never opened.
        $this->receiveProducts($manager, $cycles[1], array_slice($catalog['products'], 0, 2));
        $this->receiveProducts($manager, $cycles[1], array_slice($catalog['products'], 4, 2), firstQuantityColumn: 4);
        app(ValidateReception::class)($manager, $cycles[1], $catalog['producers'][0]);

        // A closed, ordered, unsent producer with an account is the one send the dashboard offers the manager (R9).
        app(CloseProducerNow::class)($manager, $cycles[2], $catalog['producers'][2]);

        $this->createCatalogueStates($manager, $cycles[2], $catalog);
        $this->createProducerStates($manager, $catalog);
        $this->createManagerStates($manager, $members[0]['householdId']);
        $this->enableTwoFactor();
    }

    /**
     * Created after the cycles, like the other states, so no cycle, order or count changes. The account has no passkey; passkeys
     * come from the landing fixture. The login test avoids this account because its login now asks for a code.
     */
    private function enableTwoFactor(): void
    {
        $user = User::query()->where('email', self::TWO_FACTOR_EMAIL)->firstOrFail();
        app(EnableTwoFactorAuthentication::class)($user);
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        app(ConfirmTwoFactorAuthentication::class)($user, app(Google2FA::class)->getCurrentOtp($secret));
    }

    /**
     * Created after the cycles, like the catalogue and producer states, so no cycle, order, reception or dashboard count changes:
     * the new household has no basket or movement, and the deactivated account leaves its household active through the first
     * member. The staff role with no holder is left by the grants in STAFF_ROLES. The unpaid and settled balances already show:
     * the first household's basket is collected and charged, the third and fourth have open drafts.
     */
    private function createManagerStates(Actor $manager, string $activeHouseholdId): void
    {
        app(DeactivateHousehold::class)($manager, app(CreateHousehold::class)($manager, self::FORMER_HOUSEHOLD_NAME));

        $former = $this->createAccount(Account::inviteMember(
            app(AccountRepository::class)->nextId(), self::FORMER_MEMBER_NAME, Email::from(self::FORMER_MEMBER_EMAIL), $activeHouseholdId,
        ));
        app(DeactivateAccount::class)($manager, $former->accountId);
    }

    /**
     * Created after the cycles, like the catalogue states, so no cycle, order or reception changes. The cycle with nothing to
     * deliver is not seeded: the landing fixture and Vitest cover it, and it would shift cycles, receptions and the dashboard.
     *
     * @param  array{producers: list<string>, products: list<array{id: string, producerId: string}>}  $catalog
     */
    private function createProducerStates(Actor $manager, array $catalog): void
    {
        $emptyProducerId = app(CreateProducer::class)($manager, 'Ramaderia Nova', 2, '20:00');
        $this->createAccount(Account::inviteProducer(
            app(AccountRepository::class)->nextId(), 'Ramaderia Nova', Email::from(self::EMPTY_PRODUCER_EMAIL), $emptyProducerId,
        ));

        $this->createStockStates($manager, $catalog['producers'][0]);

        $retiredWeeklyId = app(CreateProduct::class)($manager, $catalog['producers'][1], 'Coca de sucre', 'peça', 450, false, null);
        app(DeactivateProduct::class)($manager, $retiredWeeklyId);
    }

    private function createStockStates(Actor $manager, string $producerId): void
    {
        app(CreateStockProduct::class)(
            $manager, $producerId,
            'Mongetes del ganxet de la cooperativa de Santa Pau, collita ecològica de tardor',
            'bossa de mig quilo envasada al buit', 650, false, null,
        );

        $soldOutId = app(CreateStockProduct::class)($manager, $producerId, 'Llenties de la Cerdanya', 'kg', 420, true, null);
        app(RecordStockEntry::class)($manager, $soldOutId, '5.000', 'Demo opening stock');
        app(SetStockAvailable::class)($manager, $soldOutId, '0', 'Demo sold out');

        $retiredId = app(CreateStockProduct::class)($manager, $producerId, 'Fesols de Santa Pau', 'kg', 900, true, null);
        app(RecordStockEntry::class)($manager, $retiredId, '2.000', 'Demo opening stock');
        app(DeactivateStockProduct::class)($manager, $producerId, $retiredId);

        $this->createLongHistory($manager, DB::table('products')->where('name', 'Arròs integral')->value('id'));
    }

    private function createLongHistory(Actor $manager, string $productId): void
    {
        for ($count = 1; $count <= self::EXTRA_MOVEMENTS; $count++) {
            if ($count % 2 === 0) {
                app(RecordStockAdjustment::class)($manager, $productId, '-0.500', "Demo adjustment {$count}", null);

                continue;
            }
            app(RecordStockEntry::class)($manager, $productId, '1.000', "Demo restock {$count}");
        }
    }

    /**
     * Created after the cycles so no cycle, order or reception changes: the deactivated producer and the inactive product
     * never take part in a cycle, and the first producer's order in the unsent cycle still offers the availability toggle.
     *
     * @param  array{producers: list<string>, products: list<array{id: string, producerId: string}>}  $catalog
     */
    private function createCatalogueStates(Actor $manager, string $unsentCycleId, array $catalog): void
    {
        $formerProducerId = app(CreateProducer::class)($manager, 'Cooperativa Tancada', 2, '20:00');
        app(DeactivateProducer::class)($manager, $formerProducerId);

        $retiredProductId = app(CreateProduct::class)($manager, $catalog['producers'][1], 'Coca de recapte', 'peça', 420, false, null);
        app(DeactivateProduct::class)($manager, $retiredProductId);

        app(SetProductAvailability::class)($manager, $unsentCycleId, $catalog['products'][1]['id'], false);

        app(CreateReturnItem::class)($manager, 'Caixa de fruita', 150);
        $retiredReturnItemId = app(CreateReturnItem::class)($manager, 'Ampolla de vidre', 25);
        app(DeactivateReturnItem::class)($manager, $retiredReturnItemId);
    }

    /** Settlement created one draft base basket per household; the first household's is validated and collected. */
    private function collectFirstBasket(Actor $manager, string $cycleId, string $householdId): void
    {
        $basket = app(FindWeeklyBaseBasket::class)($manager, $cycleId, $householdId);
        app(ValidateBasket::class)($manager, $basket->id);
        app(MarkBasketCollected::class)($manager, $basket->id);
    }

    /** A validated, uncollected basket gives the dashboard a pending collection to show. */
    private function validateBasketAwaitingCollection(Actor $manager, string $cycleId, string $householdId): void
    {
        $basket = app(FindWeeklyBaseBasket::class)($manager, $cycleId, $householdId);
        app(ValidateBasket::class)($manager, $basket->id);
    }

    private function createAccount(Account $account): Actor
    {
        $accounts = app(AccountRepository::class);
        $accounts->save($account);
        $account->acceptInvitation(app(Clock::class)->now());
        $accounts->save($account);
        app(AccountPasswords::class)->change($account->id, self::PASSWORD);

        return app(ActorResolver::class)->forAccount($account->id);
    }

    /** @return list<array{actor: Actor, householdId: string}> */
    private function createHouseholds(Actor $manager): array
    {
        $members = [];
        foreach (self::HOUSEHOLDS as [$householdName, $name, $email, $balance]) {
            $householdId = app(CreateHousehold::class)($manager, $householdName);
            $actor = $this->createAccount(Account::inviteMember(
                app(AccountRepository::class)->nextId(), $name, Email::from($email), $householdId,
            ));
            app(RecordTopUp::class)($manager, $householdId, $balance, 'Demo opening balance');
            $members[] = ['actor' => $actor, 'householdId' => $householdId];
        }

        return $members;
    }

    /** @param list<array{actor: Actor, householdId: string}> $members */
    private function grantStaffRoles(Actor $manager, array $members): void
    {
        foreach (self::STAFF_ROLES as $memberIndex => $roles) {
            foreach ($roles as $role) {
                app(GrantStaffRole::class)($manager, $members[$memberIndex]['actor']->accountId, $role->value);
            }
        }
    }

    /** A former member whose household link was removed: no household, producer or staff role, so the dashboard explains the absence (R14). */
    private function createUnlinkedAccount(string $formerHouseholdId): void
    {
        $actor = $this->createAccount(Account::inviteMember(
            app(AccountRepository::class)->nextId(), self::UNLINKED_NAME, Email::from(self::UNLINKED_EMAIL), $formerHouseholdId,
        ));
        DB::table('users')->where('id', $actor->accountId)->update(['household_id' => null]);
    }

    /** @return array{producers: list<string>, products: list<array{id: string, producerId: string}>} */
    private function createCatalog(Actor $manager): array
    {
        $producers = [];
        $products = [];
        foreach (self::PRODUCERS as [$name, $email, $definitions]) {
            $producerId = app(CreateProducer::class)($manager, $name, 2, '20:00');
            $producer = $this->createAccount(Account::inviteProducer(
                app(AccountRepository::class)->nextId(), $name, Email::from($email), $producerId,
            ));
            $producers[] = $producerId;
            foreach ($definitions as [$productName, $unit, $price, $variable, $containers]) {
                $productId = app(CreateProduct::class)($producer, $producerId, $productName, $unit, $price, $variable, $containers);
                $products[] = ['id' => $productId, 'producerId' => $producerId];
            }
        }

        return ['producers' => $producers, 'products' => $products];
    }

    private function createStock(Actor $manager, string $producerId): void
    {
        foreach ([['Arròs integral', 'kg', 280, true, '25.000'], ['Cigrons cuits', 'pot', 210, false, '20']] as [$name, $unit, $price, $variable, $quantity]) {
            $id = app(CreateStockProduct::class)($manager, $producerId, $name, $unit, $price, $variable, null);
            app(RecordStockEntry::class)($manager, $id, $quantity, 'Demo opening stock');
        }
    }

    /** @return list<DateTimeImmutable> */
    private function pickupDates(): array
    {
        $clock = app(Clock::class);
        $date = $clock->now()->setTimezone($clock->timezone())->setTime(0, 0)->modify('+1 day');
        $pickupWeekday = app(CooperativeSettings::class)->pickupWeekday();
        $weekday = $pickupWeekday === null ? 4 : $pickupWeekday->isoDay;
        $dates = [];
        while (count($dates) < 3) {
            if ((int) $date->format('N') === $weekday && ! app(CycleRepository::class)->existsOnPickupDate($date)) {
                $dates[] = $date;
            }
            $date = $date->modify('+1 day');
        }

        return $dates;
    }

    /**
     * @param  list<array{actor: Actor, householdId: string}>  $members
     * @param  list<array{id: string, producerId: string}>  $products
     */
    private function createOrders(string $cycleId, array $members, array $products): void
    {
        foreach ($members as $memberIndex => $member) {
            foreach ($products as $productIndex => $product) {
                $quantity = self::QUANTITIES[$memberIndex][$productIndex];
                if (Quantity::fromString($quantity)->isZero()) {
                    continue;
                }
                app(SetOrderLine::class)($member['actor'], $cycleId, $product['id'], $quantity);
            }
        }
    }

    /** @param list<string> $producerIds */
    private function prepareReception(string $cycleId, array $producerIds): void
    {
        $cycles = app(CycleRepository::class);
        $cycle = $cycles->getForUpdate($cycleId) ?? throw new LogicException('Demo cycle was not created.');
        $now = app(Clock::class)->now();
        foreach ($producerIds as $producerId) {
            // Build the sent state through the domain without sending demonstration emails.
            $cycle->closeNow($producerId, $now);
            $cycle->send($producerId, $now);
        }
        $cycles->add($cycle);
    }

    /**
     * @param  list<array{id: string, producerId: string}>  $products
     * @param  int  $firstQuantityColumn  the QUANTITIES column of the first product
     */
    private function receiveProducts(Actor $manager, string $cycleId, array $products, int $firstQuantityColumn = 0): void
    {
        foreach ($products as $index => $product) {
            $thousandths = 0;
            foreach (self::QUANTITIES as $quantities) {
                $thousandths += Quantity::fromString($quantities[$firstQuantityColumn + $index])->thousandths;
            }
            app(SetReceivedTotal::class)($manager, $cycleId, $product['producerId'], $product['id'], Quantity::ofThousandths($thousandths)->toString());
        }
    }
}
