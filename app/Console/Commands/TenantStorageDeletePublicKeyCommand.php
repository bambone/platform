<?php

namespace App\Console\Commands;

use App\Support\Storage\TenantPublicMediaWriter;
use App\Support\Storage\TenantPublicObjectKey;
use Illuminate\Console\Command;

/**
 * Явное удаление объекта из зеркала и реплики (как при удалении через приложение).
 * Не вызывайте для «синхронизации» — для этого {@see TenantStorageSyncReplicaCommand}.
 */
class TenantStorageDeletePublicKeyCommand extends Command
{
    protected $signature = 'tenant-storage:delete-public-key
                            {key : Object key, например tenants/1/public/site/logo.png}
                            {--force : Без интерактивного подтверждения}';

    protected $description = 'Удаляет один публичный объект по ключу с локального зеркала и с R2 (dual/r2_only), как TenantPublicMediaWriter.';

    public function handle(TenantPublicMediaWriter $writer): int
    {
        $raw = trim((string) $this->argument('key'));
        try {
            $key = TenantPublicObjectKey::normalize($raw);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! preg_match('#^tenants/(\d+)/public/#', $key, $m)) {
            $this->error('Key must start with tenants/{id}/public/');

            return self::FAILURE;
        }

        $tenantId = (int) $m[1];

        if (! (bool) $this->option('force')) {
            if (! $this->confirm('Удалить объект с зеркала и R2: '.$key.' ?', false)) {
                return self::SUCCESS;
            }
        }

        $ok = $writer->deletePublicObjectKey($tenantId, $key);
        if (! $ok) {
            $this->warn('Удаление вернуло false (см. лог / outbox репликации).');

            return self::FAILURE;
        }

        $this->info('Удалено: '.$key);

        return self::SUCCESS;
    }
}
