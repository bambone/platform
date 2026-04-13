<?php

namespace App\Filament\Platform\Pages;

use App\Models\MediaReplicationOutbox;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Storage\EffectiveTenantMediaModeResolver;
use App\Support\Storage\MediaDeliveryMode;
use App\Support\Storage\MediaWriteMode;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class MediaStorageDeliveryPage extends Page
{
    protected static ?string $navigationLabel = 'Медиа: хранение и отдача';

    protected static ?string $title = 'Медиа: storage и delivery';

    protected static ?string $slug = 'media-storage-delivery';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Система';

    protected static ?int $navigationSort = 56;

    protected string $view = 'filament.pages.platform.media-storage-delivery';

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

        $this->getSchema('form')->fill([
            'write_mode' => (string) PlatformSetting::get('media.write_mode_default', ''),
            'delivery_mode' => (string) PlatformSetting::get('media.delivery_mode_default', ''),
            'local_public_base_path' => (string) PlatformSetting::get('media.local_public_base_path', ''),
            'r2_public_base_url' => (string) PlatformSetting::get('media.r2_public_base_url', ''),
            'local_root_display' => (string) PlatformSetting::get('media.local_root_display', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Состояние репликации')
                    ->description('Счётчики outbox R2; не используются для runtime-отдачи на сайте.')
                    ->schema([
                        Placeholder::make('outbox_pending')
                            ->label('Pending')
                            ->content(fn (): string => (string) MediaReplicationOutbox::query()->where('status', MediaReplicationOutbox::STATUS_PENDING)->count()),
                        Placeholder::make('outbox_processing')
                            ->label('Processing')
                            ->content(fn (): string => (string) MediaReplicationOutbox::query()->where('status', MediaReplicationOutbox::STATUS_PROCESSING)->count()),
                        Placeholder::make('outbox_failed')
                            ->label('Failed')
                            ->content(fn (): string => (string) MediaReplicationOutbox::query()->where('status', MediaReplicationOutbox::STATUS_FAILED)->count()),
                        Placeholder::make('effective_modes')
                            ->label('Эффективные дефолты (без учёта override клиента)')
                            ->content(function (): string {
                                $r = app(EffectiveTenantMediaModeResolver::class);

                                return 'write='.$r->effectiveWriteMode(null)->value.', delivery='.$r->effectiveDeliveryMode(null)->value;
                            }),
                    ])
                    ->columns(2),
                Section::make('Глобальные настройки')
                    ->description('Переопределяют env, если заданы. Фактический путь зеркала задаётся только на сервере (MEDIA_LOCAL_ROOT).')
                    ->schema([
                        Placeholder::make('env_media_local_root')
                            ->label('MEDIA_LOCAL_ROOT (env, только чтение)')
                            ->content(fn (): string => (string) env('MEDIA_LOCAL_ROOT', '— не задан —')),
                        TextInput::make('local_root_display')
                            ->label('Подпись / ожидаемый путь (для операторов)')
                            ->maxLength(500)
                            ->helperText('Не меняет реальный root; для справки в UI.'),
                        Select::make('write_mode')
                            ->label('Write mode по умолчанию')
                            ->options([
                                '' => '— из env / кода —',
                                MediaWriteMode::LocalOnly->value => 'local_only',
                                MediaWriteMode::R2Only->value => 'r2_only',
                                MediaWriteMode::Dual->value => 'dual',
                            ])
                            ->native(true),
                        Select::make('delivery_mode')
                            ->label('Delivery mode по умолчанию')
                            ->options([
                                '' => '— из env / кода —',
                                MediaDeliveryMode::Local->value => 'local',
                                MediaDeliveryMode::R2->value => 'r2',
                            ])
                            ->native(true),
                        TextInput::make('local_public_base_path')
                            ->label('Публичный префикс (например /media)')
                            ->maxLength(128)
                            ->placeholder('/media'),
                        TextInput::make('r2_public_base_url')
                            ->label('База публичного URL R2 (без / в конце)')
                            ->maxLength(512)
                            ->url(false),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->getSchema('form')->getState();

        $write = trim((string) ($state['write_mode'] ?? ''));
        if ($write !== '') {
            PlatformSetting::set('media.write_mode_default', $write, 'string');
        } else {
            PlatformSetting::query()->where('key', 'media.write_mode_default')->delete();
            Cache::forget('platform_settings.media.write_mode_default');
        }

        $delivery = trim((string) ($state['delivery_mode'] ?? ''));
        if ($delivery !== '') {
            PlatformSetting::set('media.delivery_mode_default', $delivery, 'string');
        } else {
            PlatformSetting::query()->where('key', 'media.delivery_mode_default')->delete();
            Cache::forget('platform_settings.media.delivery_mode_default');
        }

        $basePath = trim((string) ($state['local_public_base_path'] ?? ''));
        if ($basePath !== '') {
            PlatformSetting::set('media.local_public_base_path', $basePath, 'string');
        } else {
            PlatformSetting::query()->where('key', 'media.local_public_base_path')->delete();
            Cache::forget('platform_settings.media.local_public_base_path');
        }

        $r2 = trim((string) ($state['r2_public_base_url'] ?? ''));
        if ($r2 !== '') {
            PlatformSetting::set('media.r2_public_base_url', $r2, 'string');
        } else {
            PlatformSetting::query()->where('key', 'media.r2_public_base_url')->delete();
            Cache::forget('platform_settings.media.r2_public_base_url');
        }

        $display = trim((string) ($state['local_root_display'] ?? ''));
        if ($display !== '') {
            PlatformSetting::set('media.local_root_display', $display, 'string');
        } else {
            PlatformSetting::query()->where('key', 'media.local_root_display')->delete();
            Cache::forget('platform_settings.media.local_root_display');
        }

        Notification::make()
            ->title('Сохранено')
            ->success()
            ->send();

        $this->mount();
    }
}
