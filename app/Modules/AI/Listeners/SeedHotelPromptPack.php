<?php

declare(strict_types=1);

namespace App\Modules\AI\Listeners;

use App\Modules\AI\Actions\EnsureHotelPromptPack;
use App\Modules\Company\Events\CompanyCreated;

class SeedHotelPromptPack
{
    public function __construct(protected EnsureHotelPromptPack $ensure) {}

    public function handle(CompanyCreated $event): void
    {
        $this->ensure->handle($event->company);
    }
}
