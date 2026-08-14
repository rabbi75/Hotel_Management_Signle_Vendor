<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/**
 * A billing operation that cannot proceed for a domain reason — an exhausted
 * coupon, a plan with no gateway price, a subscription that has already ended.
 */
class BillingException extends RuntimeException {}
