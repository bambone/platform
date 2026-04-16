<?php

declare(strict_types=1);

namespace App\Services\LinkPreview;

use RuntimeException;

final class LinkPreviewUnsafeHostException extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
