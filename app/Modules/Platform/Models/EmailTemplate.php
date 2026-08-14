<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property list<string>|null $placeholders
 * @property bool $is_active
 */
class EmailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'body', 'placeholders', 'is_active'];

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    public function renderSubject(array $replacements = []): string
    {
        return $this->interpolate($this->subject, $replacements);
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    public function renderBody(array $replacements = []): string
    {
        return $this->interpolate($this->body, $replacements);
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    protected function interpolate(string $template, array $replacements): string
    {
        $search = [];
        $replace = [];

        foreach ($replacements as $key => $value) {
            $search[] = '{{'.$key.'}}';
            $replace[] = (string) ($value ?? '');
        }

        return str_replace($search, $replace, $template);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
