<?php

namespace App\MarketData;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final class Decimal
{
    public static function value(mixed $v, bool $positive = false): string
    {
        if (! is_string($v) || ! preg_match('/^-?\d{1,18}(?:\.\d{1,18})?$/D', $v)) {
            throw new InvalidArgumentException('Invalid decimal string');
        }
        $n = BigDecimal::of($v);
        if ($positive ? $n->isLessThanOrEqualTo(0) : $n->isLessThan(0)) {
            throw new InvalidArgumentException('Invalid decimal range');
        }

        return $v;
    }

    public static function signed(mixed $v): string
    {
        if (! is_string($v) || ! preg_match('/^-?\d{1,18}(?:\.\d{1,18})?$/D', $v)) {
            throw new InvalidArgumentException('Invalid signed decimal');
        }

        return $v;
    }
}
