<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

/**
 * Result of {@see ContactMapSourceParser} (link and/or iframe paste).
 */
final readonly class ContactMapParseResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $isEmpty,
        public bool $ok,
        public string $normalizedPublicUrl,
        public MapProvider $detectedProvider,
        public ?MapSourceKind $sourceKind,
        public ?string $detectionLabelRu,
        public ?string $usedSourceMessageRu,
        public array $errors,
        public array $warnings,
    ) {}

    public static function emptyInput(): self
    {
        return new self(
            isEmpty: true,
            ok: false,
            normalizedPublicUrl: '',
            detectedProvider: MapProvider::None,
            sourceKind: null,
            detectionLabelRu: null,
            usedSourceMessageRu: null,
            errors: [],
            warnings: [],
        );
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(
            isEmpty: false,
            ok: false,
            normalizedPublicUrl: '',
            detectedProvider: MapProvider::None,
            sourceKind: null,
            detectionLabelRu: null,
            usedSourceMessageRu: null,
            errors: array_values($errors),
            warnings: [],
        );
    }
}
