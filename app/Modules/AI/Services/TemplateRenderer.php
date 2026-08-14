<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\AI\Models\AiPromptTemplate;

/**
 * Substitutes `{{variable}}` placeholders in a prompt template.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function render(AiPromptTemplate $template, array $values): string
    {
        $replacements = [];

        foreach ($template->placeholders() as $name) {
            $value = $values[$name] ?? '';
            $replacements['{{'.$name.'}}'] = is_scalar($value) ? (string) $value : '';
        }

        // Tolerate whitespace inside the braces so `{{ name }}` and `{{name}}`
        // behave identically for whoever wrote the template.
        $normalised = (string) preg_replace('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', '{{$1}}', $template->prompt);

        return strtr($normalised, $replacements);
    }

    /**
     * Placeholders that were declared but left empty, so the UI can say which
     * inputs are still missing instead of silently sending a hollow prompt.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    public function missing(AiPromptTemplate $template, array $values): array
    {
        $required = [];

        foreach ($template->variables as $variable) {
            $name = $variable['name'];

            if ($variable['required'] && (! isset($values[$name]) || $values[$name] === '')) {
                $required[] = $name;
            }
        }

        return $required;
    }
}
