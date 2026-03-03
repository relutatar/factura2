<?php

namespace App\Services;

use App\Models\DocumentTemplate;

class DocumentTemplateService
{
    /**
     * Render a document template body replacing placeholders using context data.
     */
    public function render(DocumentTemplate $template, array $context): string
    {
        $body = (string) $template->body_template;

        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function (array $matches) use ($context): string {
            $key = $matches[1] ?? '';
            $value = data_get($context, $key);

            if (is_array($value) || is_object($value)) {
                return '';
            }

            return $value !== null ? (string) $value : '';
        }, $body);
    }

    /**
     * Return placeholder names available in template body.
     *
     * @return array<int, string>
     */
    public function getPlaceholders(DocumentTemplate $template): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', (string) $template->body_template, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
