<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TenantServiceProgram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Находит slug в tenant_service_programs длиннее {@see TenantServiceProgram::SLUG_MAX_LENGTH}
 * (несовместимо с публичной формой заявок и query service=…).
 */
final class AuditTenantServiceProgramInquirySlugsCommand extends Command
{
    protected $signature = 'tenant:service-programs:audit-inquiry-slugs
                            {tenant? : id или slug тенанта; без аргумента — все тенанты}';

    protected $description = 'Показать программы с slug длиннее лимита публичной формы заявок (64)';

    public function handle(): int
    {
        $max = TenantServiceProgram::SLUG_MAX_LENGTH;
        $key = $this->argument('tenant');
        $q = TenantServiceProgram::query()
            ->orderBy('tenant_id')
            ->orderBy('id');

        if ($key !== null && (string) $key !== '') {
            $tid = ctype_digit((string) $key)
                ? (int) $key
                : (int) (DB::table('tenants')->where('slug', (string) $key)->value('id') ?? 0);
            if ($tid < 1) {
                $this->error('Тенант не найден.');

                return self::FAILURE;
            }
            $q->where('tenant_id', $tid);
        }

        $rows = $q->get(['id', 'tenant_id', 'slug', 'title'])->filter(
            static fn (TenantServiceProgram $p): bool => mb_strlen(trim((string) $p->slug), 'UTF-8') > $max,
        );
        if ($rows->isEmpty()) {
            $this->info('Нет записей с slug длиннее '.$max.' символов.');

            return self::SUCCESS;
        }

        $this->warn('Найдено '.$rows->count().' записей (длина slug > '.$max.'):');
        foreach ($rows as $p) {
            $len = mb_strlen((string) $p->slug, 'UTF-8');
            $this->line(sprintf(
                'tenant_id=%d id=%d len=%d slug=%s title=%s',
                (int) $p->tenant_id,
                (int) $p->id,
                $len,
                (string) $p->slug,
                (string) $p->title,
            ));
        }

        return self::SUCCESS;
    }
}
