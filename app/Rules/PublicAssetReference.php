<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Разрешает пустое значение, legacy http(s) URL или tenant public object key {@code tenants/{id}/public/...}.
 */
final class PublicAssetReference implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)) {
            $fail(__('Некорректное значение.'));

            return;
        }
        $v = trim($value);
        if ($v === '') {
            return;
        }
        if (preg_match('#^https?://#i', $v) === 1) {
            if (filter_var($v, FILTER_VALIDATE_URL) === false) {
                $fail(__('Укажите корректный URL.'));
            }

            return;
        }
        if (preg_match('#^tenants/\d+/public/.+#', $v) !== 1) {
            $fail(__('Укажите URL или ключ файла в хранилище (tenants/…/public/…).'));

            return;
        }
    }
}
