<?php

namespace App\Tenant\StorageQuota;

use RuntimeException;

final class StorageQuotaExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public ?string $context = null,
        public ?QuotaCheckResult $check = null,
    ) {
        parent::__construct($message);
    }

    public static function defaultMessage(): string
    {
        return 'Недостаточно доступного места. Размер загружаемого файла превышает остаток квоты хранилища. Освободите место или обратитесь к администратору для расширения лимита.';
    }
}
