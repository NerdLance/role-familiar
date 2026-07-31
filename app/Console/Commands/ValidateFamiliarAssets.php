<?php

namespace App\Console\Commands;

use App\Support\Familiars\FamiliarAssetCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('familiar:validate-assets')]
#[Description('Validate the Queue Wyrm familiar asset manifest and public files')]
class ValidateFamiliarAssets extends Command
{
    public function handle(FamiliarAssetCatalog $catalog): int
    {
        try {
            $manifest = $catalog->manifest();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Queue Wyrm asset manifest is valid (version {$manifest['version']}).");

        return self::SUCCESS;
    }
}
