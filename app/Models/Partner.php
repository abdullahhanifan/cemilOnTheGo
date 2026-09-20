<?php

namespace App\Models;

use Database\Factories\PartnerFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A partner (mitra): someone who buys goods at a store on request and is paid afterwards.
 *
 * Partners are their own records, not users. Purchases and payments will reference them
 * through `partner_id`. Partners are never hard-deleted: deactivate them through `status`.
 *
 * `user_id` is reserved for a future partner login and is deliberately not fillable: nothing
 * in the application reads or writes it yet.
 *
 * The account number is encrypted with the application key (APP_KEY). It is hidden from
 * serialization, and it cannot be searched or sorted on.
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $city
 * @property string|null $area
 * @property string|null $payment_method 'bank' or 'e_wallet'.
 * @property string|null $payment_provider Free text, e.g. the bank or e-wallet name.
 * @property string|null $account_name
 * @property string|null $account_number Decrypted on read.
 * @property string|null $notes
 * @property string $status
 * @property int|null $user_id
 */
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

    /**
     * Payment methods with their labels.
     *
     * @var array<string, string>
     */
    public const PAYMENT_METHODS = [
        'bank' => 'Transfer bank',
        'e_wallet' => 'E-wallet',
    ];

    protected $fillable = [
        'name',
        'phone',
        'city',
        'area',
        'payment_method',
        'payment_provider',
        'account_name',
        'account_number',
        'notes',
        'status',
    ];

    /**
     * The account number never leaves the model through toArray() or toJson().
     *
     * @var list<string>
     */
    protected $hidden = [
        'account_number',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
        ];
    }

    public function paymentMethodLabel(): ?string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? null;
    }

    /**
     * The decrypted account number, or null when there is none or it can no longer be
     * decrypted (for example after APP_KEY changed). Reading the attribute directly throws
     * in that case, which would take a whole page down.
     */
    public function accountNumberOrNull(): ?string
    {
        try {
            return $this->account_number;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * True when an account number is stored but cannot be decrypted.
     */
    public function accountNumberIsUnreadable(): bool
    {
        return $this->getRawOriginal('account_number') !== null && $this->accountNumberOrNull() === null;
    }

    /**
     * Drop a stored account number that can no longer be decrypted, so the partner can be
     * saved again. Eloquent decrypts the old value to see whether it changed, and that throws
     * on a value that cannot be decrypted, which would make every such save fail.
     */
    public function discardUnreadableAccountNumber(): void
    {
        if (! $this->accountNumberIsUnreadable()) {
            return;
        }

        static::query()->whereKey($this->getKey())->toBase()->update(['account_number' => null]);
        $this->setRawAttributes(array_merge($this->getAttributes(), ['account_number' => null]), true);
    }

    /**
     * The account number with all but the last four digits hidden, for lists and other
     * places where the full number is not needed.
     */
    public function maskedAccountNumber(): ?string
    {
        $number = $this->accountNumberOrNull();

        if ($number === null) {
            return null;
        }

        return strlen($number) > 4 ? '•••• '.substr($number, -4) : '••••';
    }
}
