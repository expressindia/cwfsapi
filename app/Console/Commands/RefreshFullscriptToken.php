<?php

namespace App\Console\Commands;

use App\Exceptions\FullscriptOAuthException;
use App\Services\FullscriptTokenService;
use Illuminate\Console\Command;

class RefreshFullscriptToken extends Command
{
    protected $signature = 'fullscript:refresh {--if-expiring : Refresh only when the token is close to expiry}';

    protected $description = 'Refresh the single Fullscript Sandbox OAuth token.';

    public function handle(FullscriptTokenService $tokens): int
    {
        try {
            $token = $tokens->refreshIfNeeded(! $this->option('if-expiring'));
        } catch (FullscriptOAuthException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($token === null) {
            $this->warn('Fullscript is not connected; nothing to refresh.');

            return self::SUCCESS;
        }

        $this->info('Fullscript token is valid until '.$token->expires_at->toDateTimeString().'.');

        return self::SUCCESS;
    }
}
