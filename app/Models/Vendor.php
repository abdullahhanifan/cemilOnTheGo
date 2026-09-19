<?php

namespace App\Models;

use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $category
 * @property string $status
 */
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'address',
        'category',
        'status',
    ];
}
