<?php

namespace App\Policies;

use Guixa\Catalog\Domain\Product;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\Gate;

final class ProductPolicy
{
    public function update(Actor $actor, Product $product): bool
    {
        if ($product->isStock()) {
            return false;
        }

        return $this->owns($actor, $product) || Gate::forUser($actor)->allows('administer-catalogue');
    }

    public function delete(Actor $actor, Product $product): bool
    {
        return $product->isStock()
            ? $actor->hasRole(Role::Manager)
            : $this->update($actor, $product);
    }

    private function owns(Actor $actor, Product $product): bool
    {
        return $actor->hasRole(Role::Producer) && $actor->producerId === $product->producerId;
    }
}
