<?php

namespace App\Console\Commands;

use Aws\S3\Exception\S3Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TenantMediaVerifyLocalAgainstR2Command extends Command
{
    protected $signature = 'tenant-media:verify-local-against-r2
        {--target= : Absolute mirror root to compare}
        {--prefix= : S3 prefix filter}
        {--tenant= : Limit to tenants/{id}/}
        {--limit=0 : Max objects (0 = unlimited)}';

    protected $description = 'Compare R2 public objects with files on local mirror (size).';

    public function handle(): int
    {
        $target = (string) $this->option('target');
        if ($target === '') {
            $this->error('Required: --target=ABSOLUTE_PATH');

            return self::FAILURE;
        }

        if (! $this->isAbsolutePath($target)) {
            $this->error('Target must be an absolute path.');

            return self::FAILURE;
        }

        if ($this->isInsideRepo($target)) {
            $this->error('Refusing to use a path inside application base path.');

            return self::FAILURE;
        }

        $disk = Storage::disk('r2-public');
        if (! $disk instanceof FilesystemAdapter) {
            $this->error('r2-public disk must be a filesystem adapter.');

            return self::FAILURE;
        }

        try {
            $client = $disk->getClient();
        } catch (Throwable $e) {
            $this->error('r2-public disk has no S3 client: '.$e->getMessage());

            return self::FAILURE;
        }

        $bucket = (string) config('filesystems.disks.r2-public.bucket', '');
        if ($bucket === '') {
            $this->error('R2_PUBLIC_BUCKET is not configured.');

            return self::FAILURE;
        }

        $prefix = ltrim((string) $this->option('prefix'), '/');
        $tenant = $this->option('tenant');
        if ($tenant !== null && $tenant !== '') {
            $prefix = 'tenants/'.(int) $tenant.'/public/';
        }

        $limit = max(0, (int) $this->option('limit'));
        $continuationToken = null;
        $checked = 0;
        $missing = 0;
        $mismatch = 0;
        $ok = 0;

        do {
            try {
                $args = [
                    'Bucket' => $bucket,
                    'Prefix' => $prefix !== '' ? $prefix : '',
                ];
                if ($continuationToken !== null) {
                    $args['ContinuationToken'] = $continuationToken;
                }
                $result = $client->listObjectsV2($args);
            } catch (S3Exception $e) {
                $this->error('listObjectsV2 failed: '.$e->getMessage());

                return self::FAILURE;
            }

            foreach ($result['Contents'] ?? [] as $object) {
                $key = (string) ($object['Key'] ?? '');
                if ($key === '' || str_ends_with($key, '/')) {
                    continue;
                }
                if ($limit > 0 && $checked >= $limit) {
                    $continuationToken = null;
                    break 2;
                }
                $checked++;
                $remoteSize = (int) ($object['Size'] ?? 0);
                $localPath = rtrim($target, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $key);
                if (! is_file($localPath)) {
                    $missing++;
                    $this->warn('missing: '.$key);

                    continue;
                }
                $localSize = (int) filesize($localPath);
                if ($localSize !== $remoteSize) {
                    $mismatch++;
                    $this->warn('size mismatch: '.$key.' local='.$localSize.' remote='.$remoteSize);

                    continue;
                }
                $ok++;
            }

            $continuationToken = ! empty($result['IsTruncated']) ? ($result['NextContinuationToken'] ?? null) : null;
        } while ($continuationToken !== null);

        $this->table(
            ['Metric', 'Count'],
            [
                ['checked', (string) $checked],
                ['ok', (string) $ok],
                ['missing_local', (string) $missing],
                ['size_mismatch', (string) $mismatch],
            ]
        );

        return ($missing + $mismatch) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    private function isInsideRepo(string $target): bool
    {
        $base = realpath(base_path());
        if ($base === false) {
            return false;
        }
        $real = realpath($target);
        $check = $real !== false ? $real : $target;
        $baseNorm = strtolower(str_replace('\\', '/', rtrim($base, '\\/')));
        $checkNorm = strtolower(str_replace('\\', '/', rtrim($check, '\\/')));

        return $checkNorm === $baseNorm || str_starts_with($checkNorm, $baseNorm.'/');
    }
}
