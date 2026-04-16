<?php

namespace App\MediaPresentation;

/**
 * Normalized output of {@see ServiceProgramCardPresentationResolver} for one logical slot/viewport context.
 * Public templates and admin preview consume this DTO — not ad-hoc arrays.
 */
final readonly class ResolvedPresentation
{
    /**
     * @param  array<string, string|float|int>  $overlayCssVariables  names without leading -- for CSS var application
     * @param  array<string, mixed>  $safeAreaMeta  guides for admin preview (rects, labels)
     */
    public function __construct(
        public ?string $resolvedSourceUrl,
        public FocalPoint $resolvedFocal,
        public array $overlayCssVariables,
        public ?ViewportKey $activeViewportKey,
        public array $safeAreaMeta,
        public bool $missingSource,
        public bool $fallbackSourceUsed,
        public bool $legacyFocalUsed,
    ) {}

    /**
     * CSS variables for inline style on slot container (add -- prefix in template).
     *
     * @return array<string, string>
     */
    public function focalAsCssPercentVariables(string $prefix = 'svc-program-focal'): array
    {
        return [
            $prefix.'-x' => $this->resolvedFocal->x.'%',
            $prefix.'-y' => $this->resolvedFocal->y.'%',
        ];
    }
}
