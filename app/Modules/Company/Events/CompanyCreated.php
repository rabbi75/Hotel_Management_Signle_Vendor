<?php

declare(strict_types=1);

namespace App\Modules\Company\Events;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly User $owner,
    ) {}
}
