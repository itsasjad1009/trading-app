<?php

namespace App\MarketData\Contracts;

interface MarketDataProvider
{
    public function identity(): string;

    public function metadata(): array;

    public function validateSymbol(string $symbol): void;
}
