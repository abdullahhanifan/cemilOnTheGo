<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop (mall outlet, market stall, ...) where a partner can be asked to buy goods.
 *
 * Stores are never hard-deleted: deactivate them through `status` instead.
 *
 * @property int $id
 * @property string $name
 * @property string $city
 * @property string|null $area
 * @property string|null $address
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property list<array{day_from: int, day_to: int, opens_at: string, closes_at: string}>|null $opening_hours
 * @property string|null $notes
 * @property string $status
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /**
     * Day labels keyed by ISO-8601 day number (1 = Monday ... 7 = Sunday).
     *
     * @var array<int, string>
     */
    public const DAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    protected $fillable = [
        'name',
        'city',
        'area',
        'address',
        'contact_name',
        'contact_phone',
        'opening_hours',
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
            'opening_hours' => 'array',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Opening hours as display text, one line per schedule row (e.g. "Senin–Jumat 10.00–22.00").
     *
     * @return list<string>
     */
    public function openingHoursLabels(): array
    {
        return collect($this->opening_hours ?? [])
            ->map(function (array $row): string {
                $from = self::DAYS[$row['day_from']] ?? '?';
                $to = self::DAYS[$row['day_to']] ?? '?';
                $days = $row['day_from'] === $row['day_to'] ? $from : "{$from}–{$to}";
                $hours = str_replace(':', '.', $row['opens_at']).'–'.str_replace(':', '.', $row['closes_at']);

                return "{$days} {$hours}";
            })
            ->values()
            ->all();
    }
}
