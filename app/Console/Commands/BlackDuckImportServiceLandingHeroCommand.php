<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use Illuminate\Console\Command;

/**
 * Единый фон hero для всех посадочных услуг (не главной): {@code site/brand/service-landing-hero.png}.
 */
final class BlackDuckImportServiceLandingHeroCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:import-service-landing-hero
                            {tenant=blackduck : slug или id}
                            {--source= : Файл png/jpg (по умолчанию на Windows — ChatGPT Image в папке «Услуги»)}
                            {--dry-run : Без записи}';

    protected $description = 'Black Duck: картинка в шапку главной (expert_hero) и всех лендингов услуг (site/brand/service-landing-hero + site/brand/hero)';

    public function handle(BlackDuckContentRefresher $refresher): int
    {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (\Throwable) {
            $t = $refresher->resolveBlackDuckTenant();
            if ($t === null) {
                $this->error('Тенант Black Duck не найден.');

                return self::FAILURE;
            }
            $tenant = $t;
        }
        if ($tenant->theme_key !== 'black_duck') {
            $this->error('Указан тенант не black_duck.');

            return self::FAILURE;
        }

        $raw = (string) $this->option('source');
        if (trim($raw) === '') {
            if (DIRECTORY_SEPARATOR === '\\') {
                $raw = 'C:\Users\g-man\Desktop\duck\Услуги\ChatGPT Image 23 апр. 2026 г., 12_21_01.png';
            } else {
                $this->error('Укажите --source= путь к png/jpg.');

                return self::FAILURE;
            }
        }
        $raw = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        if (! is_file($raw)) {
            $this->error('Файл не найден: '.$raw);

            return self::FAILURE;
        }
        if ((bool) $this->option('dry-run')) {
            $this->info('[dry-run] Скопировали бы в '.BlackDuckContentConstants::SERVICE_LANDING_HEADER_STEM.'.{ext}');

            return self::SUCCESS;
        }

        $out = $refresher->importServiceLandingHeaderFromFile($tenant, $raw, false);
        if ($out === null) {
            $this->error('Не удалось загрузить файл в хранилище.');

            return self::FAILURE;
        }
        $this->info('OK: hero посадочных — '.$out);

        return self::SUCCESS;
    }
}
