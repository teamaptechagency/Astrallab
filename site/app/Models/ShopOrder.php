<?php

namespace App\Models;

use App\Support\Hub;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer's side of a sale.
 *
 * The hub owns the order. This is the address the customer can come back to,
 * and the place the licence key is kept once — because the hub hands it over
 * exactly once and then cannot produce it again.
 */
class ShopOrder extends Model
{
    protected $fillable = [
        'reference', 'product_slug', 'product_name', 'amount',
        'customer_name', 'customer_email', 'status',
    ];

    protected $casts = ['amount' => 'integer', 'key_saved_at' => 'datetime'];

    /**
     * Ask the hub where this order has got to, and keep the key if it is there.
     *
     * The saving is the important half. The hub answers with the key exactly
     * once; if this method displayed it without writing it down, a customer who
     * reloaded the page would have lost it for good.
     */
    public function refreshFromHub(): void
    {
        $state = Hub::order($this->reference);

        if (! $state) {
            return;
        }

        $changes = ['status' => $state['status'] ?? $this->status];

        if (! empty($state['licence_key']) && ! $this->licence_key) {
            $changes['licence_key'] = $state['licence_key'];
            $changes['key_saved_at'] = now();
        }

        // forceFill: the key is not mass-assignable, and must not be — this is
        // the only code allowed to write it.
        $this->forceFill($changes)->save();
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
