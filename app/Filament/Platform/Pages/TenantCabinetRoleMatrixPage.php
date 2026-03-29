<?php

namespace App\Filament\Platform\Pages;

use App\Auth\AccessRoles;
use App\Auth\TenantAbilityRegistry;
use App\Auth\TenantPivotPermissions;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Support\RoleLabels;
use App\Models\PlatformSetting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class TenantCabinetRoleMatrixPage extends Page
{
    protected static ?string $navigationLabel = 'Безопасность и роли кабинета';

    protected static ?string $title = 'Роли кабинета клиента';

    protected static ?string $slug = 'tenant-cabinet-security';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.platform.tenant-cabinet-role-matrix';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && ($user->hasRole('platform_owner') || $user->hasRole('platform_admin'));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $defaults = TenantPivotPermissions::defaults();
        $stored = PlatformSetting::get('tenant_pivot_permission_matrix', null);

        $matrix = [];
        foreach (AccessRoles::TENANT_MEMBERSHIP as $role) {
            $base = $defaults[$role] ?? [];
            if (is_array($stored) && isset($stored[$role]) && is_array($stored[$role])) {
                $custom = TenantAbilityRegistry::onlyRegistered($stored[$role]);
                $matrix[$role] = $custom !== [] ? $custom : $base;
            } else {
                $matrix[$role] = $base;
            }
        }

        $this->getSchema('form')->fill([
            'matrix' => $matrix,
            'tenant_login_prefer_tenant_panel' => (bool) PlatformSetting::get('tenant_login_prefer_tenant_panel', false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        $roleMeta = RoleLabels::tenantMembershipRoleLabels();

        foreach (AccessRoles::TENANT_MEMBERSHIP as $role) {
            $sections[] = Section::make(RoleLabels::labelForTenantMembershipRole($role))
                ->description($roleMeta[$role]['description'] ?? '')
                ->schema([
                    CheckboxList::make('matrix.'.$role)
                        ->label('Разрешения')
                        ->options(TenantAbilityRegistry::labels())
                        ->columns(2)
                        ->bulkToggleable(),
                ])
                ->collapsible();
        }

        $sections[] = Section::make('Поведение входа')
            ->description(FilamentInlineMarkdown::toHtml(
                'Если включено, сотрудник с ролью в консоли платформы при входе на **/admin** клиента '.
                'остаётся в кабинете клиента, а не перенаправляется на консоль платформы. '.
                'Удобно для «двойного» доступа; следите, чтобы не работать длительно в чужом кабинете по ошибке.'
            ))
            ->schema([
                Toggle::make('tenant_login_prefer_tenant_panel')
                    ->label('Не перенаправлять на платформу при входе в /admin клиента')
                    ->helperText('Глобальная настройка для всех таких пользователей.'),
            ]);

        return $schema
            ->statePath('data')
            ->components($sections);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->getSchema('form')->getState();
        $matrixRaw = $state['matrix'] ?? [];
        $out = [];

        if (is_array($matrixRaw)) {
            foreach (AccessRoles::TENANT_MEMBERSHIP as $role) {
                $picked = $matrixRaw[$role] ?? [];
                $out[$role] = TenantAbilityRegistry::onlyRegistered(is_array($picked) ? $picked : []);
            }
        }

        PlatformSetting::set('tenant_pivot_permission_matrix', $out, 'json');

        $prefer = ! empty($state['tenant_login_prefer_tenant_panel']);
        PlatformSetting::set('tenant_login_prefer_tenant_panel', $prefer, 'boolean');

        Notification::make()
            ->title('Сохранено')
            ->success()
            ->send();
    }

    public function resetMatrix(): void
    {
        abort_unless(static::canAccess(), 403);

        PlatformSetting::query()->where('key', 'tenant_pivot_permission_matrix')->delete();
        Cache::forget('platform_settings.tenant_pivot_permission_matrix');

        $current = $this->getSchema('form')->getState();
        $current['matrix'] = TenantPivotPermissions::defaults();
        $this->getSchema('form')->fill($current);

        Notification::make()
            ->title('Матрица сброшена к значениям из кода')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetMatrix')
                ->label('Сбросить матрицу к коду')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Сбросить матрицу прав?')
                ->modalDescription('Будут удалены сохранённые переопределения; снова применятся значения по умолчанию из кода. Флаг поведения входа не меняется.')
                ->action(fn () => $this->resetMatrix()),
        ];
    }
}
