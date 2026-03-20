<?php

namespace App\Filament\Support;

use App\Auth\AccessRoles;

final class RoleLabels
{
    /**
     * @return array<string, string> role name => UI label
     */
    public static function platformRoleOptions(): array
    {
        $map = self::platformRoleLabels();

        return array_combine(array_keys($map), array_column($map, 'label'));
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function platformRoleLabels(): array
    {
        return [
            'platform_owner' => [
                'label' => 'Владелец платформы',
                'description' => 'Полный доступ к консоли платформы и критичным операциям.',
            ],
            'platform_admin' => [
                'label' => 'Администратор платформы',
                'description' => 'Управление клиентами, тарифами, шаблонами и настройками.',
            ],
            'support_manager' => [
                'label' => 'Специалист поддержки',
                'description' => 'Поддержка клиентов и операционные задачи (в рамках выданных прав).',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tenantMembershipRoleOptions(): array
    {
        $map = self::tenantMembershipRoleLabels();

        return array_combine(array_keys($map), array_column($map, 'label'));
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function tenantMembershipRoleLabels(): array
    {
        return [
            'tenant_owner' => [
                'label' => 'Владелец клиента',
                'description' => 'Полный доступ к кабинету клиента и настройкам.',
            ],
            'tenant_admin' => [
                'label' => 'Администратор клиента',
                'description' => 'Управление контентом, заявками и командой (без критичных системных действий — по политикам).',
            ],
            'booking_manager' => [
                'label' => 'Менеджер бронирований',
                'description' => 'Заявки, бронирования и связь с клиентами.',
            ],
            'fleet_manager' => [
                'label' => 'Менеджер парка',
                'description' => 'Каталог, единицы парка и доступность техники.',
            ],
            'content_manager' => [
                'label' => 'Менеджер контента',
                'description' => 'Страницы, SEO и материалы сайта.',
            ],
            'operator' => [
                'label' => 'Оператор',
                'description' => 'Повседневная обработка заявок и данных.',
            ],
        ];
    }

    public static function labelForPlatformRole(string $role): string
    {
        return self::platformRoleLabels()[$role]['label'] ?? $role;
    }

    public static function labelForTenantMembershipRole(string $role): string
    {
        return self::tenantMembershipRoleLabels()[$role]['label'] ?? $role;
    }

    /**
     * @param  list<string>  $roles
     */
    public static function formatPlatformRolesList(array $roles): string
    {
        $labels = array_map(
            fn (string $r): string => self::labelForPlatformRole($r),
            array_values(array_intersect($roles, AccessRoles::platformRoles()))
        );

        return implode(', ', $labels);
    }

    /**
     * @return array<string, string> role => short description for CheckboxList
     */
    public static function platformRoleDescriptions(): array
    {
        return array_map(
            fn (array $row): string => $row['description'],
            self::platformRoleLabels()
        );
    }

    /**
     * @return array<string, string>
     */
    public static function tenantMembershipRoleDescriptions(): array
    {
        return array_map(
            fn (array $row): string => $row['description'],
            self::tenantMembershipRoleLabels()
        );
    }
}
