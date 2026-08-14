<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Enums\Severity;
use App\Modules\Company\Models\Company;
use App\Modules\Platform\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Security-relevant events that are not ordinary model changes: password
 * changes, 2FA enrolment, permission grants, session revocations.
 *
 * Ordinary create/update/delete auditing is handled by spatie/laravel-activitylog.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $company_id
 * @property SecurityEvent $event
 * @property Severity $severity
 * @property string $description
 * @property array<string, mixed>|null $context
 */
class SecurityLog extends Model
{
    protected $fillable = ['user_id', 'admin_id', 'company_id', 'event', 'severity', 'description', 'context', 'ip_address', 'user_agent'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => SecurityEvent::class,
            'severity' => Severity::class,
            'context' => 'array',
        ];
    }
}
