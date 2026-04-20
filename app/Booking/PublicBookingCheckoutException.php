<?php

declare(strict_types=1);

namespace App\Booking;

use InvalidArgumentException;

/**
 * Ожидаемая ошибка публичного checkout: сообщение для пользователя + куда редиректить и сбрасывать ли черновик.
 */
final class PublicBookingCheckoutException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly bool $forgetDraft = false,
        public readonly bool $redirectToCatalog = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
