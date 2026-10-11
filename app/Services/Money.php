<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function parse(string $value): int
    {
        if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount with at most two decimals.']);
        }
        [$whole,$fraction] = array_pad(explode('.', $value), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int $minor): string
    {
        return sprintf('%s%d.%02d', $minor < 0 ? '-' : '', intdiv(abs($minor), 100), abs($minor) % 100);
    }
}
