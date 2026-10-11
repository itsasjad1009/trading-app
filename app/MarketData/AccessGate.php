<?php

namespace App\MarketData;

use Illuminate\Http\Request;

final class AccessGate
{
    public function workerAllowed(): bool
    {
        return config('market-data.enabled') && (app()->environment('local', 'testing') || $this->publicAllowed());
    }

    public function publicAllowed(): bool
    {
        return config('market-data.public_redistribution') && config('market-data.rights_verified');
    }

    public function allows(Request $request): bool
    {
        return $this->workerAllowed() && ($this->publicAllowed() || (app()->environment('local', 'testing') && in_array($request->ip(), ['127.0.0.1', '::1']) && in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'])));
    }
}
