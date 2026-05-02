<?php

namespace App\Console\Commands;

use Aws\S3\Exception\S3Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TenantMediaBackfillFromR2Command extends Command
{
    protected $signature = 'tenant-media:backfill-from-r2
        {--target= : Absolute directory for mirror (must be outside repository)}
        {--dry-run : List objects only}
        {--only-missing : Skip if local file exists with same size}
        {--prefix= : S3 prefix filter}
        {--tenant= : Limit to tenants/{id}/}
        {--limit=0 : Max objects (0 = unlimited)}
        {--manifest=none : none|csv|json|both}
        {--skip-existing : Alias: skip when local file exists}
        {--verify-after-download : Run verify summary after download}';

    protected $description = 'Backfill public R2 objects into a local directory via S3 API (not HTTP CDN).';

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
            $this->error('Refusing to write inside application base path. Choose a directory outside the repo.');

            return self::FAILURE;
        }

        if (! is_dir($target)) {
            if (! $this->ensureDirectoryExists($target) && ! is_dir($target)) {
                $this->error('Cannot create target directory: '.$this->formatLastFilesystemError('mkdir'));

                return self::FAILURE;
            }
        }

        if (! $this->assertTargetRootWritable(rtrim($target, DIRECTORY_SEPARATOR))) {
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
        $dry = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing') || (bool) $this->option('skip-existing');
        $manifestMode = strtolower((string) $this->option('manifest'));
        $rows = [];

        $continuationToken = null;
        $processed = 0;
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        $this->info('Bucket: '.$bucket.' prefix: '.($prefix !== '' ? $prefix : '(root)'));

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
                if ($limit > 0 && $processed >= $limit) {
                    $continuationToken = null;
                    break 2;
                }
                $processed++;
                $localPath = rtrim($target, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $key);
                $size = (int) ($object['Size'] ?? 0);
                $etag = isset($object['ETag']) ? trim((string) $object['ETag'], '"') : '';
                $lastMod = isset($object['LastModified']) ? (string) $object['LastModified'] : '';

                if ($onlyMissing && is_file($localPath) && filesize($localPath) === $size) {
                    $skipped++;
                    $rows[] = $this->manifestRow($bucket, $key, $localPath, $size, $etag, $lastMod, 'skipped', '');

                    continue;
                }

                if ($dry) {
                    $this->line('[dry-run] '.$key);
                    $rows[] = $this->manifestRow($bucket, $key, $localPath, $size, $etag, $lastMod, 'dry_run', '');

                    continue;
                }

                $dir = dirname($localPath);
                if (! is_dir($dir)) {
                    error_clear_last();
                    if (! $this->ensureDirectoryExists($dir) || ! is_dir($dir)) {
                        $failed++;
                        $rows[] = $this->manifestRow(
                            $bucket,
                            $key,
                            $localPath,
                            $size,
                            $etag,
                            $lastMod,
                            'failed',
                            $this->formatLastFilesystemError('mkdir'),
                        );

                        continue;
                    }
                }

                $tmp = null;
                try {
                    $out = $client->getObject(['Bucket' => $bucket, 'Key' => $key]);
                    $body = $out['Body'] ?? null;
                    if ($body === null) {
                        throw new \RuntimeException('empty body');
                    }
                    $tmp = $localPath.'.tmp.'.bin2hex(random_bytes(6));
                    $stream = fopen($tmp, 'wb');
                    if ($stream === false) {
                        throw new \RuntimeException('tmp open');
                    }
                    while (! $body->eof()) {
                        fwrite($stream, $body->read(1024 * 1024));
                    }
                    fclose($stream);
                    if (file_exists($localPath)) {
                        @unlink($localPath);
                    }
                    if (! @rename($tmp, $localPath)) {
                        @unlink($tmp);
                        $tmp = null;
                        throw new \RuntimeException('rename failed');
                    }
                    $tmp = null;
                    $downloaded++;
                    $rows[] = $this->manifestRow($bucket, $key, $localPath, $size, $etag, $lastMod, 'downloaded', '');
                } catch (Throwable $e) {
                    if (is_string($tmp) && $tmp !== '' && file_exists($tmp)) {
                        @unlink($tmp);
                    }
                    $failed++;
                    $rows[] = $this->manifestRow($bucket, $key, $localPath, $size, $etag, $lastMod, 'failed', $e->getMessage());
                }
            }

            $continuationToken = ! empty($result['IsTruncated']) ? ($result['NextContinuationToken'] ?? null) : null;
        } while ($continuationToken !== null);

        $this->info(sprintf('Processed: %d, downloaded: %d, skipped: %d, failed: %d', $processed, $downloaded, $skipped, $failed));

        if (in_array($manifestMode, ['csv', 'both'], true)) {
            $csvPath = rtrim($target, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'manifest-backfill-'.date('Ymd-His').'.csv';
            $fh = fopen($csvPath, 'wb');
            if ($fh !== false) {
                fputcsv($fh, ['bucket', 'object_key', 'local_path', 'size', 'etag', 'last_modified', 'status', 'error_message']);
                foreach ($rows as $r) {
                    fputcsv($fh, [
                        $r['bucket'],
                        $r['object_key'],
                        $r['local_path'],
                        $r['size'],
                        $r['etag'],
                        $r['last_modified'],
                        $r['status'],
                        $r['error_message'],
                    ]);
                }
                fclose($fh);
                $this->info('Wrote '.$csvPath);
            }
        }
        if (in_array($manifestMode, ['json', 'both'], true)) {
            $jsonPath = rtrim($target, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'manifest-backfill-'.date('Ymd-His').'.json';
            file_put_contents($jsonPath, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Wrote '.$jsonPath);
        }

        if ($this->option('verify-after-download') && ! $dry) {
            $this->call('tenant-media:verify-local-against-r2', ['--target' => $target, '--prefix' => $prefix]);
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Создать дерево каталогов без проброса предупреждений наружу (в некоторых окружениях mkdir может стать исключением).
     */
    private function ensureDirectoryExists(string $absolutePath): bool
    {
        $absolutePath = trim($absolutePath);
        if ($absolutePath === '') {
            return false;
        }

        clearstatcache(true, $absolutePath);
        if (is_dir($absolutePath)) {
            return true;
        }

        error_clear_last();
        $oldMask = umask();
        umask(0002);
        try {
            if (@mkdir($absolutePath, 0775, true) === true || is_dir($absolutePath)) {
                clearstatcache(true, $absolutePath);

                return is_dir($absolutePath);
            }
        } finally {
            umask($oldMask);
        }

        clearstatcache(true, $absolutePath);

        return is_dir($absolutePath);
    }

    /** Быстро отрезает типичную ошибку пайплайна: каталог есть, но нет записи пользователю PHP (deploy vs www-data). */
    private function assertTargetRootWritable(string $targetRoot): bool
    {
        clearstatcache(true, $targetRoot);
        if (! is_dir($targetRoot)) {
            return true;
        }
        if (is_writable($targetRoot)) {
            return true;
        }

        $uidMsg = '?';
        if (function_exists('posix_geteuid')) {
            $uid = posix_geteuid();
            $name = '';
            if (function_exists('posix_getpwuid')) {
                $pw = posix_getpwuid($uid);
                $name = is_array($pw) && isset($pw['name']) ? (string) $pw['name'] : '';
            }
            $uidMsg = $name !== '' ? $name.' ('.$uid.')' : (string) $uid;
        }

        $this->error(
            'Target directory is not writable by this PHP process (user/euid='.$uidMsg.'): '.$targetRoot.'. '
            .'Ensure ownership/ACL on MEDIA_ROOT or run artisan as www-data '
            .'(GitHub Actions: variable MEDIA_BACKFILL_AS_WWW_DATA=1, see docs/operations/tenant-media-local-mirror.md).'
        );

        return false;
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    /**
     * @return array{bucket: string, object_key: string, local_path: string, size: int, etag: string, last_modified: string, status: string, error_message: string}
     */
    private function manifestRow(
        string $bucket,
        string $objectKey,
        string $localPath,
        int $size,
        string $etag,
        string $lastModified,
        string $status,
        string $errorMessage,
    ): array {
        return [
            'bucket' => $bucket,
            'object_key' => $objectKey,
            'local_path' => $localPath,
            'size' => $size,
            'etag' => $etag,
            'last_modified' => $lastModified,
            'status' => $status,
            'error_message' => $errorMessage,
        ];
    }

    private function formatLastFilesystemError(string $op): string
    {
        $last = error_get_last();
        if (is_array($last) && ($last['message'] ?? '') !== '') {
            return (string) $last['message'];
        }

        return $op.' failed (check that the process user can create directories under the target parent).';
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
