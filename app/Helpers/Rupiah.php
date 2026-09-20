<?php

namespace App\Helpers;

class Rupiah
{
    /**
     * Format whole rupiah for display, e.g. 25000 => "Rp 25.000" and -5000 => "-Rp 5.000".
     */
    public static function format(int $amount): string
    {
        $formatted = 'Rp '.number_format(abs($amount), 0, ',', '.');

        return $amount < 0 ? '-'.$formatted : $formatted;
    }
}
