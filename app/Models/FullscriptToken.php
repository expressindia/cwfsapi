<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FullscriptToken extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            // 'access_token' => 'encrypted',
            // 'refresh_token' => 'encrypted',
            'expires_at' => 'datetime'
        ];
    }

    public function expiresSoon(int $leewaySeconds = 0): bool
    {
        return $this->expires_at === null
            || $this->expires_at->lte(Carbon::now()->addSeconds($leewaySeconds));
    }
}
