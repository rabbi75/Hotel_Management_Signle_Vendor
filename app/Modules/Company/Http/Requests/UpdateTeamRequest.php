<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Models\Team;
use App\Modules\User\Models\User;

class UpdateTeamRequest extends StoreTeamRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $this->route('team');

        return $user instanceof User
            && $team instanceof Team
            && $user->can('update', $team);
    }
}
