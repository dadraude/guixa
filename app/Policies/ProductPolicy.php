<?php

namespace App\Policies;

use Guixa\Catalog\Domain\Product;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;

final class ProductPolicy
{
    public function update(Actor $actor, Product $product): bool
    {
        return ! $product->isStock()
            && $actor->hasRole(Role::Producer)
            && $actor->producerId === $product->producerId;
    }

    public function delete(Actor $actor, Product $product): bool
    {
        return $product->isStock()
            ? $actor->hasRole(Role::Manager)
            : $this->update($actor, $product);
    }
}
