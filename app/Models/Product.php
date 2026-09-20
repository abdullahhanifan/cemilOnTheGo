<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An item sold at one store. Each variant (size, flavor, ...) is a separate product.
 *
 * Prices are whole rupiah and only reference prices: invoice items snapshot them and the
 * price actually paid is recorded on the purchase. The margin is derived, never stored.
 * Products are never hard-deleted: deactivate them through `status` instead.
 *
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property string $variant Empty string when the product has no variant.
 * @property string|null $sku
 * @property string $unit
 * @property int $buy_price
 * @property int $sell_price
 * @property Carbon|null $buy_price_checked_at
 * @property string|null $notes
 * @property string $status
 * @property-read Store $store
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'variant',
        'sku',
        'unit',
        'buy_price',
        'sell_price',
        'buy_price_checked_at',
        'notes',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buy_price' => 'integer',
            'sell_price' => 'integer',
            'buy_price_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Estimated margin in rupiah: sell price minus buy price. Negative when selling below cost.
     */
    public function estimatedMargin(): int
    {
        return $this->sell_price - $this->buy_price;
    }

    /**
     * Estimated margin as a percentage of the sell price, or null when the sell price is zero.
     */
    public function estimatedMarginPercent(): ?float
    {
        if ($this->sell_price <= 0) {
            return null;
        }

        return round($this->estimatedMargin() / $this->sell_price * 100, 1);
    }
}
