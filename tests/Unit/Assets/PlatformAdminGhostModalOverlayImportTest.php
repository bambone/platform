<?php

namespace Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformAdminGhostModalOverlayImportTest extends TestCase
{
    #[Test]
    public function platform_admin_css_imports_ghost_modal_overlay_fix(): void
    {
        $platformAdmin = file_get_contents(resource_path('css/platform-admin.css'));
        $this->assertStringContainsString(
            'filament-ghost-modal-overlay.css',
            $platformAdmin,
            'platform-admin.css must @import filament-ghost-modal-overlay so the platform panel bundle ships the ghost overlay fix.',
        );

        $ghost = file_get_contents(resource_path('css/filament-ghost-modal-overlay.css'));
        $this->assertStringContainsString('.fi-modal-close-overlay', $ghost);
        $this->assertStringContainsString('.fi-modal-open', $ghost);
    }
}
