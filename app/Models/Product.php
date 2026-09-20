<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

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
 * @property string|null $photo_path Path on the public disk, or null when there is no photo.
 * @property string $status
 * @property-read Store $store
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** A product has at most one photo, kept on the public disk under a random file name. */
    public const PHOTO_DISK = 'public';

    public const PHOTO_DIRECTORY = 'products';

    /** Largest accepted photo, in kilobytes (2 MB). */
    public const PHOTO_MAX_KB = 2048;

    /** Largest accepted photo width and height, in pixels. */
    public const PHOTO_MAX_PIXELS = 6000;

    /**
     * Accepted photo types. SVG is deliberately absent: it can carry scripts.
     *
     * @var list<string>
     */
    public const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

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
        'photo_path',
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
     * Validation rules for a product photo. The type is checked on the file's
     * content, not on its client-supplied name, so renaming a file does not get
     * it past.
     *
     * @return list<string>
     */
    public static function photoRules(): array
    {
        return [
            'nullable',
            'image',
            'mimes:'.implode(',', self::PHOTO_EXTENSIONS),
            'max:'.self::PHOTO_MAX_KB,
            'dimensions:max_width='.self::PHOTO_MAX_PIXELS.',max_height='.self::PHOTO_MAX_PIXELS,
        ];
    }

    /**
     * Extension an accepted photo is stored under. It comes from the file's
     * content, never from the name the client gave it.
     *
     * @throws InvalidArgumentException When the content is not an accepted photo type.
     */
    public static function photoExtension(UploadedFile $file): string
    {
        $extension = $file->guessExtension();
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('The file is not an accepted product photo type.');
        }

        return $extension;
    }

    /**
     * Public URL of the photo, or null when there is none. Built from the current
     * request so it works whatever host or port the app is served on.
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
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
