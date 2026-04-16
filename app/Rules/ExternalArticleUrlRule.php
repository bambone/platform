<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\LinkPreview\LinkPreviewHttpUrlValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * URL статьи для {@code external_article}: только http(s), полный хост (как материал-источник).
 */
final class ExternalArticleUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $v = trim((string) $value);
        if ($v === '') {
            $fail(__('Укажите ссылку на материал.'));

            return;
        }

        $r = LinkPreviewHttpUrlValidator::validateForFetch($v);
        if (! $r['ok']) {
            $fail(__('Недопустимая ссылка: укажите полный адрес с https:// или http://.'));

            return;
        }
    }
}
