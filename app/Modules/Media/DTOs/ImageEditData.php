<?php

declare(strict_types=1);

namespace App\Modules\Media\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

/**
 * The operations one pass of the image editor should apply, in order:
 * rotate, then crop, then scale.
 */
readonly class ImageEditData extends Data
{
    public function __construct(
        public ?int $rotate = null,
        public ?int $cropX = null,
        public ?int $cropY = null,
        public ?int $cropWidth = null,
        public ?int $cropHeight = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $quality = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            rotate: self::intOrNull($request, 'rotate'),
            cropX: self::intOrNull($request, 'crop_x'),
            cropY: self::intOrNull($request, 'crop_y'),
            cropWidth: self::intOrNull($request, 'crop_width'),
            cropHeight: self::intOrNull($request, 'crop_height'),
            width: self::intOrNull($request, 'width'),
            height: self::intOrNull($request, 'height'),
            quality: self::intOrNull($request, 'quality'),
        );
    }

    public function hasCrop(): bool
    {
        return $this->cropWidth !== null && $this->cropHeight !== null;
    }

    public function hasOperations(): bool
    {
        return ($this->rotate !== null && $this->rotate % 360 !== 0)
            || $this->hasCrop()
            || $this->width !== null
            || $this->height !== null;
    }

    protected static function intOrNull(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
