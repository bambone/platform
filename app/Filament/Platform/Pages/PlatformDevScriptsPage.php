<?php

namespace App\Filament\Platform\Pages;

use App\Auth\AccessRoles;
use App\Models\User;
use App\Support\DevScripts\DevScriptRegistry;
use App\Support\DevScripts\DevScriptRunner;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Throwable;
use UnitEnum;

class PlatformDevScriptsPage extends Page
{
    protected static ?string $navigationLabel = 'Dev-скрипты';

    protected static ?string $title = 'Запуск скриптов из /scripts';

    protected static ?string $slug = 'dev-scripts';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Система';

    protected static ?int $navigationSort = 95;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationBadge = 'local';

    protected string $view = 'filament.pages.platform.dev-scripts';

    /** @var array<string, mixed>|null */
    public ?array $lastRun = null;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->hasAnyRole(AccessRoles::platformRoles())
            && app()->environment('local');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->getSchema('form')->fill([
            'media_target' => (string) env('MEDIA_LOCAL_ROOT', ''),
            'dump_path' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Параметры запуска')
                    ->description('Подставляются в сценарии при нажатии «Запустить». Почта (MAIL_*) для этих операций не используется.')
                    ->schema([
                        TextInput::make('media_target')
                            ->label('Каталог локального зеркала медиа')
                            ->helperText('Абсолютный путь. Можно оставить пустым, если задан MEDIA_LOCAL_ROOT в .env — тогда подставится он.')
                            ->placeholder('C:\OSPanel\home\rentbase-media')
                            ->maxLength(512),
                        TextInput::make('dump_path')
                            ->label('Путь к .sql (полный импорт)')
                            ->helperText('Только для сценария «Полный импорт дампа». Абсолютный путь к одному .sql файлу.')
                            ->placeholder('C:\path\to\platform.sql')
                            ->maxLength(512),
                    ])
                    ->columns(2),
            ]);
    }

    public function runScript(string $scriptId): void
    {
        abort_unless(static::canAccess(), 403);

        $allowed = collect(DevScriptRegistry::availableScripts())->pluck('id')->all();
        if (! in_array($scriptId, $allowed, true)) {
            Notification::make()->title('Неизвестный или недоступный сценарий')->danger()->send();

            return;
        }

        try {
            $runner = app(DevScriptRunner::class);
            $result = $runner->run($scriptId, [
                'media_target' => (string) ($this->data['media_target'] ?? ''),
                'dump_path' => (string) ($this->data['dump_path'] ?? ''),
            ]);
            $this->lastRun = array_merge($result, [
                'finished_at' => now()->toIso8601String(),
            ]);
            if ($result['success']) {
                Notification::make()
                    ->title('Скрипт завершился успешно')
                    ->body('Код выхода: '.(string) ($result['exit_code'] ?? '—'))
                    ->success()
                    ->send();
            } else {
                $out = trim((string) ($result['output'] ?? ''));
                $hint = $out === ''
                    ? 'Вывод пуст: у процесса PHP (веб-сервера) в PATH должны быть те же утилиты, что в терминале (mysql, mysqldump, zstd, rclone, php). Полный лог — в блоке «Последний результат» ниже.'
                    : Str::limit($out, 450);
                Notification::make()
                    ->title('Скрипт завершился с ошибкой')
                    ->body('Код выхода: '.(string) ($result['exit_code'] ?? '—')."\n\n".$hint)
                    ->danger()
                    ->send();
            }
        } catch (InvalidArgumentException $e) {
            $this->lastRun = [
                'success' => false,
                'exit_code' => null,
                'output' => $e->getMessage(),
                'script_id' => $scriptId,
                'label' => '',
                'finished_at' => now()->toIso8601String(),
            ];
            Notification::make()->title('Проверьте параметры')->body($e->getMessage())->warning()->send();
        } catch (Throwable $e) {
            $this->lastRun = [
                'success' => false,
                'exit_code' => null,
                'output' => $e->getMessage(),
                'script_id' => $scriptId,
                'label' => '',
                'finished_at' => now()->toIso8601String(),
            ];
            report($e);
            Notification::make()->title('Сбой выполнения')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function scripts(): array
    {
        return DevScriptRegistry::availableScripts();
    }
}
