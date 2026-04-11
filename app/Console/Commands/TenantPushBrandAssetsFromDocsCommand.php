<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Http\Controllers\HomeController;
use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Загружает отобранные JPG из docs/tenants_tz/{slug}/ в {@code tenants/{id}/public/site/brand/}
 * (диск {@see TenantStorageDisks::publicDiskName()} — R2/S3 или local public).
 */
final class TenantPushBrandAssetsFromDocsCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:push-brand-assets-from-docs
                            {tenant=aflyatunov : slug или id тенанта}
                            {--dry-run : Только показать план}
                            {--skip-cleanup : Не удалять public/tenants/{slug} после успеха}
                            {--intro-video= : Абсолютный путь к intro MP4 (загрузка в site/brand/video-intro.mp4)}
                            {--only-intro-video : Только видео, без JPG из docs/tenants_tz}';

    protected $description = 'Upload expert brand images from docs/tenants_tz to tenant public storage (site/brand/*.jpg)';

    /**
     * Имя на диске => имя исходного файла в docs/tenants_tz/{slug}/.
     *
     * @var array<string, string>
     */
    private const AFLYATUNOV_SOURCES = [
        // Hero = тот же кадр, что gallery-2 (инструктор между машинами на снегу), не грязевой RVM1 и не только «брызги».
        'hero.jpg' => '9ZFRE_a6qRt6nv4rZnC6DWkc6AukL1l8NKd1G188ZUoh-w18MQ51x8NRr59m77Zu8bse2u7tpk-EJC8kfSKVjVmX.jpg',
        'gallery-1.jpg' => 'aG4tubA-b7OX3OEu5nwG8H6qH5CeoNX3SngrQOyrrhGadbqRG0w2_8kCnPvsPSRmYSfqzWTq7aUFYqfVjovvChOw.jpg',
        'gallery-2.jpg' => 'uzWtsTjuJ9bWZkHqaTcQTqJWPWvHJMUydG3UGgkMQiIRuSYbMoRW4GgJgoIx1oei2MfGGdR5vaVvrJBmm_8A6idD.jpg',
        'gallery-3.jpg' => 'RVM1WLeO4qG7Nmk8xhVRmPbC5eNwzN8mqffSINbKAt_Cvtlpb6U9l7OQIrF_zeTlEMcdO8kCI-8DlZINOEsYuKhm.jpg',
        'portrait.jpg' => 'XJfSyqMeoLUBYofocC0hPsVFcGFqHNsHP5ZoY1MCETNM27mcH9ZeCebxnuN6W_gWQKWZU3YA.jpg',
        'credentials-bg.jpg' => 'vxuR83OeYf0aEFtx_HDTjm7LUIly-am1EEUVHoIbaU5iM4QrTLeeTclcJXPAWNrk74vH0Bcketj3KUh9DWBC2e4s.jpg',
        'process-accent.jpg' => '1xuCpiN_NhtGBTPTkz-pEu0BW8aOWCQxxhjW0Z_De8JCubxsYXTtVeCQ1W-5gFd5_U7rjmWXN8dOOfdC7INURJMK.jpg',
    ];

    private const INTRO_VIDEO_LOGICAL = 'site/brand/video-intro.mp4';

    public function handle(): int
    {
        $key = trim((string) $this->argument('tenant'));
        $tenant = $this->resolveTenant($key);
        $slug = $tenant->slug;

        $onlyVideo = (bool) $this->option('only-intro-video');
        $introVideoPath = trim((string) $this->option('intro-video'));

        if ($onlyVideo && $introVideoPath === '') {
            $this->error('С флагом --only-intro-video нужно указать --intro-video=путь\\к\\файлу.mp4');

            return self::FAILURE;
        }

        $docsDir = base_path('docs/tenants_tz/'.$slug);
        if (! $onlyVideo) {
            if (! is_dir($docsDir)) {
                $this->error('Нет каталога с исходниками: '.$docsDir);

                return self::FAILURE;
            }
        }

        $map = $slug === 'aflyatunov' ? self::AFLYATUNOV_SOURCES : null;
        if (! $onlyVideo) {
            if ($map === null) {
                $this->error('Для slug «'.$slug.'» нет таблицы соответствия файлов. Добавьте маппинг в команду или передайте тенанта aflyatunov.');

                return self::FAILURE;
            }
        }

        $diskName = TenantStorageDisks::publicDiskName();
        $this->info("Диск: `{$diskName}` → tenants/{$tenant->id}/public/site/brand/");

        $dry = (bool) $this->option('dry-run');
        $storage = TenantStorage::forTrusted($tenant);

        if (! $onlyVideo) {
            foreach ($map as $destName => $srcName) {
                $srcPath = $docsDir.DIRECTORY_SEPARATOR.$srcName;
                if (! is_file($srcPath)) {
                    $this->error('Нет файла: '.$srcPath);

                    return self::FAILURE;
                }
                $logical = 'site/brand/'.$destName;
                $this->line($srcName.' → '.$logical);
                if ($dry) {
                    continue;
                }
                $putOptions = ['visibility' => 'public'];
                $ct = self::contentTypeForBrandFile($destName);
                if ($ct !== null) {
                    $putOptions['ContentType'] = $ct;
                }
                $ok = $storage->putPublic($logical, File::get($srcPath), $putOptions);
                if (! $ok) {
                    $this->error('Не удалось записать: '.$logical);

                    return self::FAILURE;
                }
            }
        }

        if ($introVideoPath !== '') {
            $introVideoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $introVideoPath);
            if (! is_file($introVideoPath)) {
                $this->error('Нет файла видео: '.$introVideoPath);

                return self::FAILURE;
            }
            $ext = strtolower(pathinfo($introVideoPath, PATHINFO_EXTENSION));
            if ($ext !== 'mp4') {
                $this->error('Ожидается .mp4, получено: .'.$ext);

                return self::FAILURE;
            }
            $this->line(basename($introVideoPath).' → '.self::INTRO_VIDEO_LOGICAL);
            if (! $dry) {
                $contents = File::get($introVideoPath);
                $ok = $storage->putPublic(self::INTRO_VIDEO_LOGICAL, $contents, [
                    'visibility' => 'public',
                    'ContentType' => 'video/mp4',
                ]);
                if (! $ok) {
                    $this->error('Не удалось записать: '.self::INTRO_VIDEO_LOGICAL);

                    return self::FAILURE;
                }
                // Новая версия → новый query v=… (ExpertBrandMediaUrl дописывает к video-intro.mp4)
                $cacheVer = substr(hash('sha256', $contents), 0, 16);
                TenantSetting::setForTenant((int) $tenant->id, 'brand.intro_video_ver', $cacheVer);
                $videoUrl = $storage->publicUrl(self::INTRO_VIDEO_LOGICAL).'?v='.rawurlencode($cacheVer);
                $this->info('Intro video URL (с cache-bust): '.$videoUrl);
                $this->patchExpertHeroVideoUrl((int) $tenant->id, $videoUrl);
            }
        }

        if ($dry) {
            $this->warn('[dry-run] Загрузка не выполнялась.');

            return self::SUCCESS;
        }

        if (! $onlyVideo) {
            $this->info('Публичный URL примера (JPG): '.$storage->publicUrl('site/brand/gallery-2.jpg'));
        }

        HomeController::forgetCachedPayloadForTenant((int) $tenant->id);

        if (! $this->option('skip-cleanup')) {
            $publicLegacy = public_path('tenants/'.$slug);
            if (is_dir($publicLegacy)) {
                File::deleteDirectory($publicLegacy);
                $this->info('Удалён локальный каталог: '.$publicLegacy);
            } else {
                $this->comment('Нет каталога для очистки: '.$publicLegacy);
            }
        }

        return self::SUCCESS;
    }

    private function patchExpertHeroVideoUrl(int $tenantId, string $videoUrl): void
    {
        $pageId = (int) DB::table('pages')
            ->where('tenant_id', $tenantId)
            ->where('slug', 'home')
            ->value('id');
        if ($pageId <= 0) {
            $this->warn('Нет страницы home — hero_video_url в БД не обновлён. URL: '.$videoUrl);

            return;
        }

        $rows = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', 'expert_hero')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Нет секции expert_hero на главной — вставьте URL вручную в конструкторе: '.$videoUrl);

            return;
        }

        foreach ($rows as $row) {
            $data = json_decode((string) $row->data_json, true) ?: [];
            $data['hero_video_url'] = $videoUrl;
            if (trim((string) ($data['video_trigger_label'] ?? '')) === '') {
                $data['video_trigger_label'] = 'Смотреть, как проходят занятия';
            }
            DB::table('page_sections')->where('id', $row->id)->update([
                'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        $this->info('Обновлён hero_video_url в секции expert_hero (главная).');
    }

    private static function contentTypeForBrandFile(string $destName): ?string
    {
        return match (strtolower(pathinfo($destName, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            default => null,
        };
    }
}
