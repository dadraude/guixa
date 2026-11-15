<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Guixa\Sales\Application\MarkBasketCollected;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Shared\Application\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class BasketCollectionController extends Controller
{
    public function update(string $basket, Actor $actor, MarkBasketCollected $collect): RedirectResponse
    {
        try {
            $collect($actor, $basket);
        } catch (BasketNotEditable) {
            throw ValidationException::withMessages(['domain' => __('errors.BasketNotCollectable')]);
        }

        return back();
    }
}
