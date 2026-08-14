<?php

declare(strict_types=1);

namespace App\Modules\Media\Exceptions;

use RuntimeException;

/**
 * A media operation refused for a domain reason — a file over the configured
 * ceiling, a folder that still has contents, a move that would create a cycle.
 */
class MediaException extends RuntimeException {}
