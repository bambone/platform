<?php

namespace Tests\Feature\Tenant;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Tests\TestCase;

class TenantStorageSyncReplicaCommandTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $this->deleteDirectory($root);
        }

        $this->tempRoots = [];

        Mockery::close();

        parent::tearDown();
    }

    public function test_exits_failure_when_disk_unknown(): void
    {
        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--left-public' => 'disk-that-does-not-exist-xyz',
            '--right-public' => 'public',
        ])->assertExitCode(1);
    }

    public function test_exits_failure_when_tenant_option_is_invalid(): void
    {
        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--tenant' => 'abc',
            '--left-public' => 'public',
            '--right-public' => 'public',
        ])->assertExitCode(1);
    }

    public function test_exits_success_when_public_disks_identical(): void
    {
        $this->artisan('tenant-storage:sync-replica', [
            '--dry-run' => true,
            '--scope' => 'public',
            '--left-public' => 'public',
            '--right-public' => 'public',
        ])->assertExitCode(0);
    }

    public function test_pushes_missing_file_to_right_disk(): void
    {
        $this->registerLocalDisk('sync-left');
        $this->registerLocalDisk('sync-right');

        Storage::disk('sync-left')->put('tenants/1/public/site/a.txt', 'hello');

        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--left-public' => 'sync-left',
            '--right-public' => 'sync-right',
            '--prefix' => 'tenants/1/',
        ])->assertExitCode(0);

        $this->assertTrue(Storage::disk('sync-right')->exists('tenants/1/public/site/a.txt'));
        $this->assertSame('hello', Storage::disk('sync-right')->get('tenants/1/public/site/a.txt'));
    }

    public function test_pulls_missing_file_to_left_disk(): void
    {
        $this->registerLocalDisk('sync-left');
        $this->registerLocalDisk('sync-right');

        Storage::disk('sync-right')->put('tenants/1/public/site/b.txt', 'world');

        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--left-public' => 'sync-left',
            '--right-public' => 'sync-right',
            '--prefix' => 'tenants/1/',
        ])->assertExitCode(0);

        $this->assertTrue(Storage::disk('sync-left')->exists('tenants/1/public/site/b.txt'));
        $this->assertSame('world', Storage::disk('sync-left')->get('tenants/1/public/site/b.txt'));
    }

    public function test_dry_run_does_not_write_missing_file(): void
    {
        $this->registerLocalDisk('sync-left');
        $this->registerLocalDisk('sync-right');

        Storage::disk('sync-left')->put('tenants/1/public/site/c.txt', 'dry-run');

        $this->artisan('tenant-storage:sync-replica', [
            '--dry-run' => true,
            '--scope' => 'public',
            '--left-public' => 'sync-left',
            '--right-public' => 'sync-right',
            '--prefix' => 'tenants/1/',
        ])->assertExitCode(0);

        $this->assertFalse(Storage::disk('sync-right')->exists('tenants/1/public/site/c.txt'));
    }

    public function test_conflict_skip_keeps_existing_target_content(): void
    {
        $this->registerLocalDisk('sync-left');
        $this->registerLocalDisk('sync-right');

        Storage::disk('sync-left')->put('tenants/1/public/site/conflict.txt', 'left-content');
        Storage::disk('sync-right')->put('tenants/1/public/site/conflict.txt', 'right');

        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--left-public' => 'sync-left',
            '--right-public' => 'sync-right',
            '--prefix' => 'tenants/1/',
            '--on-conflict' => 'skip',
        ])->assertExitCode(0);

        $this->assertSame('left-content', Storage::disk('sync-left')->get('tenants/1/public/site/conflict.txt'));
        $this->assertSame('right', Storage::disk('sync-right')->get('tenants/1/public/site/conflict.txt'));
    }

    public function test_conflict_prefer_left_overwrites_right(): void
    {
        $this->registerLocalDisk('sync-left');
        $this->registerLocalDisk('sync-right');

        Storage::disk('sync-left')->put('tenants/1/public/site/conflict-left.txt', 'left-is-source');
        Storage::disk('sync-right')->put('tenants/1/public/site/conflict-left.txt', 'tiny');

        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--left-public' => 'sync-left',
            '--right-public' => 'sync-right',
            '--prefix' => 'tenants/1/',
            '--on-conflict' => 'prefer-left',
        ])->assertExitCode(0);

        $this->assertSame('left-is-source', Storage::disk('sync-right')->get('tenants/1/public/site/conflict-left.txt'));
    }

    public function test_conflict_prefer_right_overwrites_left(): void
    {
        $this->registerLocalDisk('sync-left');
        $this->registerLocalDisk('sync-right');

        Storage::disk('sync-left')->put('tenants/1/public/site/conflict-right.txt', 'tiny');
        Storage::disk('sync-right')->put('tenants/1/public/site/conflict-right.txt', 'right-is-source');

        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--left-public' => 'sync-left',
            '--right-public' => 'sync-right',
            '--prefix' => 'tenants/1/',
            '--on-conflict' => 'prefer-right',
        ])->assertExitCode(0);

        $this->assertSame('right-is-source', Storage::disk('sync-left')->get('tenants/1/public/site/conflict-right.txt'));
    }

    public function test_exits_failure_when_listing_files_fails(): void
    {
        config([
            'filesystems.disks.broken-left' => [
                'driver' => 'local',
                'root' => sys_get_temp_dir().'/broken-left-'.uniqid('', true),
                'throw' => false,
            ],
            'filesystems.disks.broken-right' => [
                'driver' => 'local',
                'root' => sys_get_temp_dir().'/broken-right-'.uniqid('', true),
                'throw' => false,
            ],
        ]);

        $left = Mockery::mock(Filesystem::class);
        $left->shouldReceive('allFiles')
            ->once()
            ->with('tenants')
            ->andThrow(new RuntimeException('listing boom'));

        $right = Mockery::mock(Filesystem::class);

        Storage::partialMock()
            ->shouldReceive('disk')
            ->twice()
            ->andReturnUsing(function (string $name) use ($left, $right) {
                return match ($name) {
                    'broken-left' => $left,
                    'broken-right' => $right,
                    default => throw new RuntimeException('Unexpected disk ['.$name.']'),
                };
            });

        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--left-public' => 'broken-left',
            '--right-public' => 'broken-right',
            '--prefix' => 'tenants/',
        ])->assertExitCode(1);
    }

    public function test_exits_failure_when_stat_size_fails(): void
    {
        config([
            'filesystems.disks.stat-left' => [
                'driver' => 'local',
                'root' => sys_get_temp_dir().'/stat-left-'.uniqid('', true),
                'throw' => false,
            ],
            'filesystems.disks.stat-right' => [
                'driver' => 'local',
                'root' => sys_get_temp_dir().'/stat-right-'.uniqid('', true),
                'throw' => false,
            ],
        ]);

        $left = Mockery::mock(Filesystem::class);
        $left->shouldReceive('allFiles')
            ->once()
            ->with('tenants')
            ->andReturn(['tenants/1/public/site/a.txt']);
        $left->shouldReceive('size')
            ->once()
            ->with('tenants/1/public/site/a.txt')
            ->andThrow(new RuntimeException('stat boom'));

        $right = Mockery::mock(Filesystem::class);

        Storage::partialMock()
            ->shouldReceive('disk')
            ->twice()
            ->andReturnUsing(function (string $name) use ($left, $right) {
                return match ($name) {
                    'stat-left' => $left,
                    'stat-right' => $right,
                    default => throw new RuntimeException('Unexpected disk ['.$name.']'),
                };
            });

        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'public',
            '--left-public' => 'stat-left',
            '--right-public' => 'stat-right',
            '--prefix' => 'tenants/',
        ])->assertExitCode(1);
    }

    public function test_scope_both_syncs_public_and_private_segments(): void
    {
        $this->registerLocalDisk('sync-left-public');
        $this->registerLocalDisk('sync-right-public');
        $this->registerLocalDisk('sync-left-private');
        $this->registerLocalDisk('sync-right-private');

        Storage::disk('sync-left-public')->put('tenants/1/public/site/logo.txt', 'public-data');
        Storage::disk('sync-right-private')->put('tenants/1/private/seo/token.txt', 'private-data');

        $this->artisan('tenant-storage:sync-replica', [
            '--scope' => 'both',
            '--left-public' => 'sync-left-public',
            '--right-public' => 'sync-right-public',
            '--left-private' => 'sync-left-private',
            '--right-private' => 'sync-right-private',
            '--prefix' => 'tenants/1/',
        ])->assertExitCode(0);

        $this->assertSame('public-data', Storage::disk('sync-right-public')->get('tenants/1/public/site/logo.txt'));
        $this->assertSame('private-data', Storage::disk('sync-left-private')->get('tenants/1/private/seo/token.txt'));
    }

    private function registerLocalDisk(string $diskName): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.$diskName.'-'.uniqid('', true);

        if (! is_dir($root) && ! mkdir($root, 0777, true) && ! is_dir($root)) {
            throw new RuntimeException('Unable to create test disk root: '.$root);
        }

        $this->tempRoots[] = $root;

        config([
            "filesystems.disks.{$diskName}" => [
                'driver' => 'local',
                'root' => $root,
                'throw' => false,
            ],
        ]);
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
