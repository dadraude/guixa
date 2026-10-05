<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * The fixed set of permissions of the app. Only administrators assign them,
 * and only to commissions; members get them through the commissions they are in.
 */
enum Role: string
{
    case Orders = 'orders';
    case Boxes = 'boxes';
    case Treasury = 'treasury';
    case Membership = 'membership';

    /**
     * The Catalan name, always read from the translation files.
     */
    public function label(): string
    {
        return match ($this) {
            self::Orders => __('Orders role'),
            self::Boxes => __('Boxes role'),
            self::Treasury => __('Treasury role'),
            self::Membership => __('Membership role'),
        };
    }

    /**
     * @return array{value: string, label: string}
     */
    public function toOption(): array
    {
        return ['value' => $this->value, 'label' => $this->label()];
    }
}
