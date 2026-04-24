<?php

declare(strict_types=1);

namespace App\Services\TenantFiles;

use App\Models\PageSection;
use App\Models\TenantMediaAsset;
use App\Models\TenantServiceProgram;
use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Ищет вероятные ссылки на публичный object key в настройках и контенте тенанта (мягкий guard перед delete).
 *
 * Подстроки поиска покрывают типичные форматы: полный ключ {@code tenants/{id}/public/...}, логический путь
 * {@code site/...|themes/...|media/...}, префикс {@code public/...}, варианты с ведущим «/», а также
 * JSON-экранирование слэшей ({@code \/}) в {@code data_json}.
 */
final class TenantPublicFileReferenceFinder
{
    public const MAX_REFERENCES = 30;

    /**
     * Все варианты подстрок для LIKE-поиска (тесты и соглашение о поддерживаемых форматах ссылок).
     *
     * @return list<string>
     */
    public function needleVariantsForObjectKey(int $tenantId, string $objectKey): array
    {
        $objectKey = str_replace('\\', '/', trim($objectKey));
        if ($objectKey === '') {
            return [];
        }

        return $this->searchNeedles($tenantId, $objectKey);
    }

    /**
     * @return list<string> Короткие подписи для уведомления.
     */
    public function findReferenceLabels(int $tenantId, string $objectKey): array
    {
        $objectKey = str_replace('\\', '/', trim($objectKey));
        if ($objectKey === '') {
            return [];
        }

        $needles = $this->searchNeedles($tenantId, $objectKey);
        if ($needles === []) {
            return [];
        }

        $out = [];
        foreach ($this->refsFromPageSections($tenantId, $needles) as $l) {
            $out[] = $l;
            if (count($out) >= self::MAX_REFERENCES) {
                return $out;
            }
        }
        foreach ($this->refsFromServicePrograms($tenantId, $needles) as $l) {
            $out[] = $l;
            if (count($out) >= self::MAX_REFERENCES) {
                return $out;
            }
        }
        foreach ($this->refsFromMediaCatalogRows($tenantId, $needles) as $l) {
            $out[] = $l;
            if (count($out) >= self::MAX_REFERENCES) {
                return $out;
            }
        }
        foreach ($this->refsFromTenantSettings($tenantId, $needles) as $l) {
            $out[] = $l;
            if (count($out) >= self::MAX_REFERENCES) {
                return $out;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function searchNeedles(int $tenantId, string $objectKey): array
    {
        $out = [
            $objectKey,
            str_replace('/', '\\/', $objectKey),
        ];
        if (preg_match('#^tenants/\d+/public/(.+)$#', $objectKey, $m) === 1) {
            $rel = (string) $m[1];
            if ($rel !== '') {
                $out[] = $rel;
                $out[] = str_replace('/', '\\/', $rel);
                $publicRel = 'public/'.$rel;
                $out[] = $publicRel;
                $out[] = str_replace('/', '\\/', $publicRel);
                if (! str_starts_with($rel, '/')) {
                    $lead = '/'.$rel;
                    $out[] = $lead;
                    $out[] = str_replace('/', '\\/', $lead);
                }
            }
        } elseif (preg_match('#^public/(.+)$#', $objectKey, $m2) === 1) {
            $fromPublic = (string) $m2[1];
            if ($fromPublic !== '') {
                $out[] = $fromPublic;
                $out[] = str_replace('/', '\\/', $fromPublic);
            }
        } else {
            $ts = TenantStorage::forTrusted($tenantId);
            foreach (['site/', 'themes/', TenantStorage::MEDIA_FOLDER.'/'] as $prefix) {
                if (str_starts_with($objectKey, $prefix)) {
                    $out[] = $objectKey;

                    break;
                }
            }
        }

        return array_values(array_unique(array_filter($out, static fn (string $s): bool => $s !== '')));
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function refsFromPageSections(int $tenantId, array $needles): array
    {
        if ($needles === []) {
            return [];
        }

        $q = PageSection::query()
            ->where('tenant_id', $tenantId);
        $this->applyJsonTextContainsAny($q, 'data_json', $needles);
        $q->with('page:id,name,slug');

        $rows = $q->limit(40)->get(['id', 'page_id', 'section_type', 'section_key']);
        $out = [];
        foreach ($rows as $row) {
            $page = $row->page;
            $pname = $page ? (string) $page->name : '?';
            $st = (string) ($row->section_type ?? '');
            $sk = (string) ($row->section_key ?? '');
            $out[] = __('Страница «:page», секция :type / :key', [
                'page' => $pname,
                'type' => $st !== '' ? $st : '—',
                'key' => $sk !== '' ? $sk : '—',
            ]);
        }

        return $out;
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function refsFromServicePrograms(int $tenantId, array $needles): array
    {
        if ($needles === []) {
            return [];
        }

        $conn = TenantServiceProgram::query()->getModel()->getConnection();
        $castP = $this->charCastForConnection($conn, 'cover_presentation_json');
        $castC = Schema::hasColumn('tenant_service_programs', 'catalog_meta_json')
            ? $this->charCastForConnection($conn, 'catalog_meta_json')
            : null;

        $q = TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $b) use ($needles, $castP, $castC): void {
                foreach ($needles as $needle) {
                    $likeStr = '%'.$this->escapeLike($needle).'%';
                    $likeJson = '%'.$this->escapeLikeWildcardsOnly($needle).'%';
                    $b->orWhere('cover_image_ref', 'like', $likeStr)
                        ->orWhere('cover_mobile_ref', 'like', $likeStr)
                        ->orWhereRaw("{$castP} like ?", [$likeJson]);
                    if ($castC !== null) {
                        $b->orWhereRaw("{$castC} like ?", [$likeJson]);
                    }
                }
            });

        $rows = $q->limit(30)->get(['id', 'title', 'slug']);
        $out = [];
        foreach ($rows as $row) {
            $t = (string) ($row->title ?? '');
            $s = (string) ($row->slug ?? '');
            $out[] = __('Услуга / программа: :title (slug: :slug)', [
                'title' => $t !== '' ? $t : (string) $row->id,
                'slug' => $s !== '' ? $s : '—',
            ]);
        }

        return $out;
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function refsFromMediaCatalogRows(int $tenantId, array $needles): array
    {
        if ($needles === []) {
            return [];
        }

        $q = TenantMediaAsset::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $b) use ($needles): void {
                foreach ($needles as $needle) {
                    $like = '%'.$this->escapeLike($needle).'%';
                    $b->orWhere('logical_path', 'like', $like)
                        ->orWhere('poster_logical_path', 'like', $like)
                        ->orWhere('source_ref', 'like', $like);
                }
            });

        $rows = $q->limit(30)->get(['id', 'title', 'role', 'logical_path']);
        $out = [];
        foreach ($rows as $row) {
            $out[] = __('Каталог медиа (DB): :title — :path', [
                'title' => (string) ($row->title ?? $row->id),
                'path' => (string) ($row->logical_path ?? ''),
            ]).(trim((string) ($row->role ?? '')) !== '' ? ' ('.$row->role.')' : '');
        }

        return $out;
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function refsFromTenantSettings(int $tenantId, array $needles): array
    {
        if ($needles === []) {
            return [];
        }

        $q = TenantSetting::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $b) use ($needles): void {
                foreach ($needles as $needle) {
                    $b->orWhere('value', 'like', '%'.$this->escapeLike($needle).'%');
                }
            });

        $rows = $q->limit(20)->get(['id', 'group', 'key']);
        $out = [];
        foreach ($rows as $row) {
            $g = (string) ($row->group ?? '');
            $k = (string) ($row->key ?? '');
            $out[] = __('Настройка: :gk', ['gk' => $g !== '' ? $g.'.'.$k : $k]);
        }

        return $out;
    }

    private function charCastForConnection(Connection $conn, string $col): string
    {
        $driver = $conn->getDriverName();

        return match ($driver) {
            'pgsql' => "({$col}::text)",
            'sqlite' => "cast({$col} as text)",
            default => "cast({$col} as char)", // mysql, mariadb
        };
    }

    /**
     * @param  list<string>  $needles
     */
    private function applyJsonTextContainsAny(Builder $b, string $col, array $needles): void
    {
        $conn = $b->getModel()->getConnection();
        $cast = $this->charCastForConnection($conn, $col);
        $b->where(function (Builder $inner) use ($cast, $needles): void {
            foreach ($needles as $needle) {
                $inner->orWhereRaw("{$cast} like ?", [
                    '%'.$this->escapeLikeWildcardsOnly($needle).'%',
                ]);
            }
        });
    }

    private function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);
    }

    private function escapeLikeWildcardsOnly(string $s): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $s);
    }
}
