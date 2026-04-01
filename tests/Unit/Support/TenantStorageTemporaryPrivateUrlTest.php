<?php

namespace Tests\Unit\Support;

use App\Support\Storage\TenantStorage;
use LogicException;
use Tests\TestCase;

class TenantStorageTemporaryPrivateUrlTest extends TestCase
{
    public function test_temporary_private_url_throws_when_private_disk_is_local(): void
    {
        config(['tenant_storage.private_disk' => 'local']);

        $this->expectException(LogicException::class);
        TenantStorage::forTrusted(1)->temporaryPrivateUrl('site/seo/robots.txt', now()->addHour());
    }
}
