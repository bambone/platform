<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Tenant;

use App\Filament\Tenant\Resources\TenantServiceProgramResource\Concerns\RefreshesBlackDuckTenantPublicContent;
use App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages\CreateTenantServiceProgram;
use App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages\EditTenantServiceProgram;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Сохранение каталога услуг в админке должно триггерить публичный refresh (трейт на страницах).
 */
final class TenantServiceProgramResourceRefreshHookTest extends TestCase
{
    #[Test]
    public function edit_and_create_use_refresher_trait(): void
    {
        $traits = class_uses_recursive(EditTenantServiceProgram::class);
        $this->assertContains(RefreshesBlackDuckTenantPublicContent::class, $traits, 'Edit page must mix in Black Duck refresh');
        $traitsC = class_uses_recursive(CreateTenantServiceProgram::class);
        $this->assertContains(RefreshesBlackDuckTenantPublicContent::class, $traitsC, 'Create page must mix in Black Duck refresh');
    }
}
