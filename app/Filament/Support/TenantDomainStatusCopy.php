<?php

namespace App\Filament\Support;

/**
 * Человекочитаемые статусы домена и подсказки «что делать дальше».
 * Значения согласовать с фактическими данными в БД; неизвестные значения обрабатываются как сырой текст с нейтральной подсказкой.
 */
final class TenantDomainStatusCopy
{
    /**
     * @return array<string, string>
     */
    public static function verificationOptions(): array
    {
        return [
            'pending' => 'Ожидает проверки',
            'verifying' => 'Проверяется',
            'verified' => 'Подтверждён',
            'failed' => 'Проверка не пройдена',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sslOptions(): array
    {
        return [
            'pending' => 'Не выпущен',
            'issuing' => 'Выпускается',
            'active' => 'Активен',
            'error' => 'Ошибка',
        ];
    }

    public static function verificationLabel(?string $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        return self::verificationOptions()[$state] ?? $state;
    }

    public static function sslLabel(?string $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        return self::sslOptions()[$state] ?? $state;
    }

    public static function verificationNextStep(?string $state, ?string $dnsTarget, string $host): string
    {
        return match ($state) {
            'pending', 'verifying' => $dnsTarget
                ? 'Добавьте у регистратора DNS-запись по инструкции платформы. Цель: '.$dnsTarget.'.'
                : 'Добавьте DNS-записи у регистратора домена согласно инструкции подключения домена.',
            'failed' => 'Проверьте DNS-записи и совпадение имени домена ('.$host.'). После исправления запустите проверку снова (если доступно).',
            'verified' => 'Домен подтверждён. При необходимости назначьте его основным для сайта клиента.',
            default => 'При необходимости обратитесь к документации по подключению домена или в поддержку.',
        };
    }

    public static function sslNextStep(?string $state): string
    {
        return match ($state) {
            'pending' => 'После подтверждения домена сертификат обычно выпускается автоматически.',
            'issuing' => 'Подождите несколько минут. Убедитесь, что DNS уже указывает на платформу.',
            'active' => 'Сертификат действует. Сайт можно открывать по HTTPS.',
            'error' => 'Проверьте DNS и доступность домена. Частая причина — запись ещё не распространилась.',
            default => 'Статус SSL обновится после корректной настройки DNS и проверки домена.',
        };
    }
}
