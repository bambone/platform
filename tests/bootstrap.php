<?php

declare(strict_types=1);

/**
 * Загружается до vendor/autoload и до Laravel.
 *
 * Если запустить `phpunit` с `--no-configuration` или без env из phpunit.xml,
 * подтянется .env с MySQL — трейт RefreshDatabase делает migrate:fresh и сотрёт
 * реальную базу (в т.ч. users). Здесь принудительно изолируем тесты на sqlite :memory:.
 *
 * Явный opt-out (отдельная тестовая MySQL и т.п.): RENTBASE_TEST_USE_ENV_DATABASE=1
 */
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

require dirname(__DIR__).'/vendor/autoload.php';
