<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CopyBundledHeroVideoToTenantCommand extends Command
{
    protected $signature = 'tenant:copy-bundled-hero-video
                            {tenant_id : ID клиента}
                            {--filename=Moto_levins_1.mp4 : Имя файла в videos bundled-темы}';

    protected $description = 'Копирует hero-MP4 из tenants/_system/themes/…/videos/ в tenants/{id}/public/site/videos/ на публичном диске.';

    public function handle(): int
    {
        $id = (int) $this->argument('tenant_id');
        $tenant = Tenant::query()->find($id);
        if ($tenant === null) {
            $this->error('Клиент с таким ID не найден.');

            return self::FAILURE;
        }

        $filename = basename((string) $this->option('filename'));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $this->error('Некорректное имя файла.');

            return self::FAILURE;
        }

        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        $candidates = array_unique([
            'tenants/_system/themes/'.$tenant->themeKey().'/videos/'.$filename,
            'tenants/_system/themes/moto/videos/'.$filename,
            'tenants/_system/themes/default/videos/'.$filename,
        ]);

        $from = null;
        foreach ($candidates as $key) {
            if ($disk->exists($key)) {
                $from = $key;
                break;
            }
        }

        if ($from === null) {
            $this->error('На диске не найден bundled-файл. Проверены ключи: '.implode(', ', $candidates));

            return self::FAILURE;
        }

        $to = TenantStorage::forTrusted($tenant)->publicPath('site/videos/'.$filename);
        if (! $disk->copy($from, $to)) {
            $this->error('Ошибка копирования объекта в хранилище.');

            return self::FAILURE;
        }

        $this->info("Скопировано: {$from} → {$to}");
        $this->line('В секции Hero главной страницы укажите путь: site/videos/'.$filename);

        return self::SUCCESS;
    }
}
