<?php

declare(strict_types=1);

namespace App\Modules\SEO\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * The verdict of a single SEO check.
 *
 * Deliberately three-valued: a bare pass/fail forces genuinely advisory
 * findings ("this page has no outbound links") into a failure they do not
 * deserve, and users learn to ignore a checklist that cries wolf.
 */
enum CheckStatus: string
{
    use HasLabel;

    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
}
