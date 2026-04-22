<?php

declare(strict_types=1);

return [
    /*
    | Ссылки для CTA «подключите push-услугу / поддержка» в кабинете тенанта.
    | Если оба пусты — в UI используется fallback-текст без обязательной ссылки.
    */
    'support_url' => env('TENANT_PUSH_SUPPORT_URL', ''),
    'support_email' => env('TENANT_PUSH_SUPPORT_EMAIL', ''),
];
