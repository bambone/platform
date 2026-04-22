<?php

namespace Tests;

use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        // При --no-configuration phpunit не подключает tests/bootstrap.php — выставляем до загрузки .env (safeLoad не перезапишет).
        if (! filter_var(getenv('RENTBASE_TEST_USE_ENV_DATABASE') ?: '', FILTER_VALIDATE_BOOLEAN)) {
            $_ENV['APP_ENV'] = 'testing';
            $_SERVER['APP_ENV'] = 'testing';
            putenv('APP_ENV=testing');
            $_ENV['DB_CONNECTION'] = 'sqlite';
            $_ENV['DB_DATABASE'] = ':memory:';
            $_ENV['DB_URL'] = '';
            putenv('DB_CONNECTION=sqlite');
            putenv('DB_DATABASE=:memory:');
            putenv('DB_URL=');
        }

        $app = parent::createApplication();
        $this->assertTestDatabaseWillNotWipeDevelopmentData($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // CI runs `composer test` without `npm run build`; Filament layouts use @vite and would 500 without manifest.
        $this->withoutVite();

        // Разрешает TenantStorage::for($id) в unit-тестах без HTTP-тенанта; в feature-тестах middleware подменит binding.
        app()->instance(CurrentTenant::class, new CurrentTenant(null, null, true));
    }

    /**
     * Последняя линия защиты: RefreshDatabase → migrate:fresh на «живой» MySQL стирает все таблицы.
     */
    private function assertTestDatabaseWillNotWipeDevelopmentData(Application $app): void
    {
        if (! $app->environment('testing')) {
            return;
        }
        if (filter_var(getenv('RENTBASE_TEST_USE_ENV_DATABASE') ?: '', FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $config = $app->make('config');
        $default = (string) $config->get('database.default', 'sqlite');
        $driver = (string) $config->get("database.connections.{$default}.driver", $default);
        $database = $config->get("database.connections.{$default}.database");

        $sqliteOk = $driver === 'sqlite'
            && ($database === ':memory:' || (is_string($database) && str_ends_with($database, 'testing.sqlite')));

        if ($sqliteOk) {
            return;
        }

        throw new RuntimeException(
            'Refusing to run tests: default DB would receive migrate:fresh (RefreshDatabase) and destroy real data. '.
            'Use tests/bootstrap.php + phpunit.xml (sqlite :memory:), or set RENTBASE_TEST_USE_ENV_DATABASE=1 with a dedicated test database. '.
            "Got connection [{$default}] driver [{$driver}] database [".json_encode($database).'].'
        );
    }
}
