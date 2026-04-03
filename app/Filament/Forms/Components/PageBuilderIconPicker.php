<?php

namespace App\Filament\Forms\Components;

use App\PageBuilder\PageBuilderIconCatalog;
use Closure;
use Filament\Forms\Components\Field;

final class PageBuilderIconPicker extends Field
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.page-builder-icon-picker';

    protected string|Closure $catalogGroup = 'features';

    protected bool|Closure $allowLegacyFallback = false;

    public function catalogGroup(string|Closure $group): static
    {
        $this->catalogGroup = $group;

        return $this;
    }

    public function allowLegacyFallback(bool|Closure $allow = true): static
    {
        $this->allowLegacyFallback = $allow;

        return $this;
    }

    public function getCatalogGroup(): string
    {
        return $this->evaluate($this->catalogGroup) ?? 'features';
    }

    public function isLegacyFallbackAllowed(): bool
    {
        return (bool) $this->evaluate($this->allowLegacyFallback);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules([
            'nullable',
            'string',
            'max:64',
            function (string $attribute, mixed $value, Closure $fail): void {
                PageBuilderIconCatalog::validateIconValue(
                    $value,
                    $this->getCatalogGroup(),
                    $this->isLegacyFallbackAllowed(),
                    $fail,
                );
            },
        ]);
    }
}
