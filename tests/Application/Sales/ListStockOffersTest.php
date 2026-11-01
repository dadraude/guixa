<?php

use Guixa\Sales\Application\ListStockOffers;
use Guixa\Sales\Domain\StockOfferCatalog;
use Guixa\Sales\Domain\StockOfferProduct;
use Guixa\Sales\Domain\StockQuantities;
use Guixa\Shared\Application\Actor;
use Illuminate\Auth\Access\AuthorizationException;

it('lists active stock including zero and inactive producers without changing stock', function () {
    $catalog = new class implements StockOfferCatalog
    {
        public function active(): array
        {
            return [
                new StockOfferProduct('honey', 'beekeeper', 'Beekeeper', 'Honey', 'jar', 1250),
                new StockOfferProduct('oil', 'oil-maker', 'Oil maker', 'Oil', 'bottle', 450),
            ];
        }
    };
    $quantities = new class implements StockQuantities
    {
        public array $calls = [];

        public function forProducts(array $ids): array
        {
            $this->calls[] = $ids;

            return ['honey' => '5.000'];
        }
    };

    $offers = (new ListStockOffers($catalog, $quantities))(Actor::memberOf('member', 'household'));

    expect(array_map(fn ($offer) => [$offer->id, $offer->producerName, $offer->priceCents, $offer->available, $offer->soldOut], $offers))
        ->toBe([['honey', 'Beekeeper', 1250, '5.000', false], ['oil', 'Oil maker', 450, '0.000', true]])
        ->and($quantities->calls)->toBe([['honey', 'oil']]);
});

it('rejects a producer-only account from the member stock catalogue', function () {
    $catalog = Mockery::mock(StockOfferCatalog::class);
    $quantities = Mockery::mock(StockQuantities::class);
    $catalog->shouldNotReceive('active');

    (new ListStockOffers($catalog, $quantities))(Actor::producer('producer', 'beekeeper'));
})->throws(AuthorizationException::class);
