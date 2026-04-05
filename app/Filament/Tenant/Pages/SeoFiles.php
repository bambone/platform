<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use App\Models\TenantSeoFile;
use App\Models\TenantSeoFileGeneration;
use App\Models\TenantSetting;
use App\Services\Seo\InitializeTenantSeoDefaults;
use App\Services\Seo\PublicContentLastUpdatedService;
use App\Services\Seo\SeoFileStorage;
use App\Services\Seo\SitemapFreshnessService;
use App\Services\Seo\TenantCanonicalPublicBaseUrl;
use App\Services\Seo\TenantSeoAutopilotService;
use App\Services\Seo\TenantSeoFilePublisher;
use App\Services\Seo\TenantSeoLintService;
use App\Services\Seo\TenantSeoPublicContentService;
use App\Services\Seo\TenantSeoSnapshotReader;
use App\Support\Storage\TenantStorage;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use JsonException;
use Throwable;
use UnitEnum;

class SeoFiles extends Page
{
    protected static ?string $navigationLabel = 'SEO-файлы';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $title = 'SEO: robots.txt, sitemap.xml, llms.txt';

    protected static ?string $slug = 'seo-files';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected string $view = 'filament.pages.seo-files';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage_seo_files')
            || Gate::allows('manage_seo')
            || Gate::allows('manage_settings');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if(currentTenant() === null, 403);

        $this->data = $this->loadFormState();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('robots.txt')
                    ->description('Публичный URL: '.e($this->publicRobotsUrl()).' · Снимок хранится отдельно от «живого» fallback.')
                    ->schema([
                        Placeholder::make('robots_snapshot')
                            ->label('Снимок (файл)')
                            ->content(fn (): string => $this->robotsSnapshotValid() ? 'Есть' : 'Нет'),
                        Placeholder::make('robots_generated')
                            ->label('Сгенерирован')
                            ->content(fn (): string => $this->robotsRow()?->generated_at?->format('Y-m-d H:i') ?? '—'),
                        Placeholder::make('robots_checksum')
                            ->label('Checksum (SHA-256)')
                            ->content(function (): string {
                                $h = $this->robotsRow()?->checksum;

                                return $h ? substr($h, 0, 16).'…' : '—';
                            }),
                        Placeholder::make('robots_size')
                            ->label('Размер')
                            ->content(fn (): string => $this->robotsRow()?->size_bytes !== null
                                ? (string) $this->robotsRow()->size_bytes.' B'
                                : '—'),
                        Placeholder::make('robots_backup')
                            ->label('Последний backup (путь)')
                            ->content(fn (): string => $this->robotsRow()?->backup_storage_path ?? '—'),
                    ])->columns(2),

                Section::make('sitemap.xml')
                    ->description('Публичный URL: '.e($this->publicSitemapUrl()).' · При выключенной sitemap сайт отдаёт 404.')
                    ->schema([
                        Placeholder::make('sitemap_snapshot')
                            ->label('Снимок (файл)')
                            ->content(fn (): string => $this->sitemapSnapshotValid() ? 'Есть' : 'Нет'),
                        Placeholder::make('sitemap_generated')
                            ->label('Сгенерирован')
                            ->content(fn (): string => $this->sitemapRow()?->generated_at?->format('Y-m-d H:i') ?? '—'),
                        Placeholder::make('sitemap_freshness')
                            ->label('Актуальность')
                            ->content(fn (): string => $this->sitemapFreshnessLabel()),
                        Placeholder::make('sitemap_content_updated')
                            ->label('Последнее изменение публичного контента')
                            ->content(fn (): string => $this->lastPublicContentLabel()),
                        Placeholder::make('sitemap_checksum')
                            ->label('Checksum (SHA-256)')
                            ->content(function (): string {
                                $h = $this->sitemapRow()?->checksum;

                                return $h ? substr($h, 0, 16).'…' : '—';
                            }),
                        Placeholder::make('sitemap_backup')
                            ->label('Последний backup (путь)')
                            ->content(fn (): string => $this->sitemapRow()?->backup_storage_path ?? '—'),
                    ])->columns(2),

                Section::make('Настройки SEO')
                    ->description('Индексация, robots/sitemap, экспериментальный /llms.txt и переопределения title/description для публичных маршрутов без страницы в CMS. JSON для llms и маршрутов хранится строкой; при ошибке формата сохранение блокируется. Предпросмотр robots — кнопка в шапке.')
                    ->schema([
                        Toggle::make('seo_indexing_enabled')
                            ->label('Индексация включена')
                            ->helperText('seo.indexing_enabled'),
                        Toggle::make('seo_sitemap_enabled')
                            ->label('Sitemap включена (публичный /sitemap.xml)')
                            ->helperText('seo.sitemap_enabled'),
                        Toggle::make('seo_custom_robots_enabled')
                            ->label('Использовать пользовательские правила robots')
                            ->helperText('seo.custom_robots_enabled: при включении и непустом поле ниже отдаётся именно этот текст (иначе — шаблон из allow/disallow).'),
                        Textarea::make('seo_robots_txt')
                            ->label('Полный текст robots.txt (при пользовательском режиме)')
                            ->rows(10)
                            ->columnSpanFull()
                            ->placeholder("User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: https://…/sitemap.xml")
                            ->helperText('Ключ в БД: seo.robots_txt. Имеет эффект только если включено «Использовать пользовательские правила» и поле не пустое.'),
                        Toggle::make('seo_robots_include_sitemap')
                            ->label('В шаблоне robots добавлять строку Sitemap:')
                            ->helperText('seo.robots_include_sitemap'),
                        TextInput::make('seo_sitemap_stale_after_days')
                            ->label('Sitemap «устарела по возрасту» через, дней')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->helperText('seo.sitemap_stale_after_days'),
                        Toggle::make('seo_sitemap_auto_schedule')
                            ->label('Автопересборка sitemap по расписанию (ночной job)')
                            ->helperText('seo.sitemap_auto_regenerate_on_schedule'),
                        Textarea::make('seo_robots_allow_paths_json')
                            ->label('Allow paths (JSON-массив строк)')
                            ->rows(2)
                            ->helperText('seo.robots_allow_paths, по умолчанию ["/"]'),
                        Textarea::make('seo_robots_disallow_paths_json')
                            ->label('Disallow paths (JSON-массив строк)')
                            ->rows(2)
                            ->helperText('seo.robots_disallow_paths, по умолчанию ["/admin","/api"]'),
                        Textarea::make('seo_llms_intro')
                            ->label('Введение для llms.txt')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('seo.llms_intro — 2–4 строки о бизнесе и сайте. Показывается под заголовком с названием сайта.'),
                        Textarea::make('seo_llms_entries_json')
                            ->label('Список URL для llms.txt (JSON)')
                            ->rows(8)
                            ->columnSpanFull()
                            ->helperText('seo.llms_entries: массив [{"path":"/","summary":"…"},…]. Если пусто — пути из sitemap без описаний.'),
                        Textarea::make('seo_route_overrides_json')
                            ->label('Переопределения SEO по имени маршрута (JSON)')
                            ->rows(10)
                            ->columnSpanFull()
                            ->helperText('seo.route_overrides: объект { "faq": { "title", "description", "h1" }, … }. Плейсхолдеры: {site_name}, {page_name}, {motorcycle_name}.'),
                    ])->columns(2),
            ]);
    }

    public function saveSettings(): void
    {
        abort_unless(
            Gate::allows('manage_seo_files') || Gate::allows('manage_settings'),
            403
        );

        $tenant = currentTenant();
        abort_if($tenant === null, 403);

        $state = $this->getSchema('form')->getState();

        if (! $this->validateTenantSeoJsonFields($state)) {
            return;
        }

        try {
            $allow = $this->decodeJsonArray((string) ($state['seo_robots_allow_paths_json'] ?? ''), 'Allow paths');
            $disallow = $this->decodeJsonArray((string) ($state['seo_robots_disallow_paths_json'] ?? ''), 'Disallow paths');
        } catch (JsonException $e) {
            Notification::make()
                ->title('Некорректный JSON')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        TenantSetting::setForTenant($tenant->id, 'seo.indexing_enabled', ! empty($state['seo_indexing_enabled']), 'boolean');
        TenantSetting::setForTenant($tenant->id, 'seo.sitemap_enabled', ! empty($state['seo_sitemap_enabled']), 'boolean');
        TenantSetting::setForTenant($tenant->id, 'seo.custom_robots_enabled', ! empty($state['seo_custom_robots_enabled']), 'boolean');
        TenantSetting::setForTenant(
            $tenant->id,
            'seo.robots_txt',
            trim((string) ($state['seo_robots_txt'] ?? '')),
            'string',
        );
        TenantSetting::setForTenant($tenant->id, 'seo.robots_include_sitemap', ! empty($state['seo_robots_include_sitemap']), 'boolean');
        TenantSetting::setForTenant($tenant->id, 'seo.sitemap_stale_after_days', max(1, min(365, (int) ($state['seo_sitemap_stale_after_days'] ?? 7))), 'integer');
        TenantSetting::setForTenant($tenant->id, 'seo.sitemap_auto_regenerate_on_schedule', ! empty($state['seo_sitemap_auto_schedule']), 'boolean');
        if ($allow === []) {
            $allow = ['/'];
        }
        if ($disallow === []) {
            $disallow = ['/admin', '/api'];
        }

        TenantSetting::setForTenant($tenant->id, 'seo.robots_allow_paths', $allow, 'json');
        TenantSetting::setForTenant($tenant->id, 'seo.robots_disallow_paths', $disallow, 'json');

        TenantSetting::setForTenant(
            $tenant->id,
            'seo.llms_intro',
            trim((string) ($state['seo_llms_intro'] ?? '')),
            'string',
        );
        TenantSetting::setForTenant(
            $tenant->id,
            'seo.llms_entries',
            trim((string) ($state['seo_llms_entries_json'] ?? '')),
            'string',
        );
        TenantSetting::setForTenant(
            $tenant->id,
            'seo.route_overrides',
            trim((string) ($state['seo_route_overrides_json'] ?? '')),
            'string',
        );

        Notification::make()->title('Настройки сохранены')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        $base = app(TenantCanonicalPublicBaseUrl::class)->resolve($tenant);

        return [
            Action::make('previewRobots')
                ->label('Предпросмотр robots')
                ->modalHeading('Предпросмотр robots.txt')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalContent(fn () => view('filament.partials.seo-text-preview', [
                    'content' => app(TenantSeoPublicContentService::class)->robotsBody($tenant),
                ]))
                ->modalSubmitAction(false),

            Action::make('previewSitemap')
                ->label('Предпросмотр sitemap')
                ->visible(fn (): bool => (bool) TenantSetting::getForTenant($tenant->id, 'seo.sitemap_enabled', true))
                ->modalHeading('Предпросмотр sitemap.xml')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalContent(fn () => view('filament.partials.seo-text-preview', [
                    'content' => app(TenantSeoPublicContentService::class)->sitemapBodyForEnabledTenant($tenant),
                ]))
                ->modalSubmitAction(false),

            Action::make('openRobots')
                ->label('Открыть robots.txt')
                ->url($base.'/robots.txt')
                ->openUrlInNewTab(),

            Action::make('openSitemap')
                ->label('Открыть sitemap.xml')
                ->visible(fn (): bool => (bool) TenantSetting::getForTenant($tenant->id, 'seo.sitemap_enabled', true))
                ->url($base.'/sitemap.xml')
                ->openUrlInNewTab(),

            Action::make('generateRobotsFirst')
                ->label('Сгенерировать robots.txt')
                ->icon('heroicon-o-document-plus')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') && ! $this->robotsSnapshotValid())
                ->requiresConfirmation()
                ->modalHeading('Сгенерировать robots.txt?')
                ->modalDescription('Будет создан снимок файла для публичного URL.')
                ->action(function () use ($tenant): void {
                    $this->runPublishRobots($tenant, false, false);
                }),

            Action::make('generateRobotsOverwrite')
                ->label('Перезаписать robots.txt')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') && $this->robotsSnapshotValid())
                ->form([
                    Toggle::make('create_backup')
                        ->label('Создать резервную копию перед перезаписью')
                        ->default(true),
                ])
                ->modalHeading('Файл robots.txt уже существует')
                ->modalDescription('Генерация создаст новую версию и перезапишет текущий публичный снимок.')
                ->modalSubmitActionLabel('Перезаписать robots.txt')
                ->action(function (array $data) use ($tenant): void {
                    $this->runPublishRobots($tenant, true, (bool) ($data['create_backup'] ?? true));
                }),

            Action::make('generateSitemapFirst')
                ->label('Сгенерировать sitemap')
                ->icon('heroicon-o-map')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') && ! $this->sitemapSnapshotValid())
                ->requiresConfirmation()
                ->modalHeading('Сгенерировать sitemap.xml?')
                ->action(function () use ($tenant): void {
                    $this->runPublishSitemap($tenant, false);
                }),

            Action::make('generateSitemapRebuild')
                ->label('Пересобрать sitemap')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') && $this->sitemapSnapshotValid())
                ->form([
                    Toggle::make('create_backup')
                        ->label('Создать резервную копию перед перезаписью')
                        ->default(true),
                ])
                ->modalSubmitActionLabel('Пересобрать sitemap.xml')
                ->action(function (array $data) use ($tenant): void {
                    $this->runPublishSitemap($tenant, (bool) ($data['create_backup'] ?? true));
                }),

            Action::make('downloadRobotsBackup')
                ->label('Скачать backup robots')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') && filled($this->robotsRow()?->backup_storage_path))
                ->action(function () use ($tenant): mixed {
                    return $this->streamBackupDownload($tenant, $this->robotsRow()?->backup_storage_path);
                }),

            Action::make('downloadSitemapBackup')
                ->label('Скачать backup sitemap')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') && filled($this->sitemapRow()?->backup_storage_path))
                ->action(function () use ($tenant): mixed {
                    return $this->streamBackupDownload($tenant, $this->sitemapRow()?->backup_storage_path);
                }),

            Action::make('seoAutopilotBootstrap')
                ->label('SEO autopilot: значения по умолчанию')
                ->icon('heroicon-o-sparkles')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') || Gate::allows('manage_settings'))
                ->requiresConfirmation()
                ->modalHeading('Сгенерировать SEO по умолчанию?')
                ->modalDescription('Заполняет llms.txt, переопределения маршрутов и при необходимости мету главной. Уже заполненные поля не перезаписываются.')
                ->action(function () use ($tenant): void {
                    abort_unless(Gate::allows('manage_seo_files') || Gate::allows('manage_settings'), 403);
                    $result = app(InitializeTenantSeoDefaults::class)->execute($tenant, false, false);
                    $this->data = $this->loadFormState();
                    $body = $result->messages !== [] ? implode("\n", $result->messages) : 'Изменений нет (поля уже заполнены).';
                    Notification::make()->title('SEO autopilot')->body($body)->success()->send();
                }),

            Action::make('seoAutopilotRefreshLlms')
                ->label('Обновить llms из данных сайта')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') || Gate::allows('manage_settings'))
                ->requiresConfirmation()
                ->modalHeading('Перезаписать llms.txt в настройках?')
                ->modalDescription('Обновляет только поля введения и списка ссылок для /llms.txt.')
                ->action(function () use ($tenant): void {
                    abort_unless(Gate::allows('manage_seo_files') || Gate::allows('manage_settings'), 403);
                    app(TenantSeoAutopilotService::class)->refreshLlmsOnly($tenant, false);
                    $this->data = $this->loadFormState();
                    Notification::make()->title('llms обновлён')->success()->send();
                }),

            Action::make('seoAutopilotLint')
                ->label('Проверка SEO (lint)')
                ->icon('heroicon-o-shield-check')
                ->visible(fn (): bool => Gate::allows('manage_seo_files') || Gate::allows('manage_settings'))
                ->modalHeading('Результат проверки SEO')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalContent(function () use ($tenant): View {
                    abort_unless(Gate::allows('manage_seo_files') || Gate::allows('manage_settings'), 403);
                    $lint = app(TenantSeoLintService::class)->lint($tenant, false);

                    return view('filament.partials.seo-lint-result', [
                        'result' => $lint,
                    ]);
                })
                ->modalSubmitAction(false),
        ];
    }

    private function runPublishRobots(Tenant $tenant, bool $overwriteConfirmed, bool $createBackup): void
    {
        Gate::authorize('manage_seo_files');
        try {
            app(TenantSeoFilePublisher::class)->publishRobots(
                $tenant,
                Auth::id(),
                TenantSeoFileGeneration::SOURCE_MANUAL,
                $overwriteConfirmed,
                $createBackup,
            );
            Notification::make()->title('robots.txt опубликован')->success()->send();
            $this->dispatch('$refresh');
        } catch (Throwable $e) {
            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
        }
    }

    private function runPublishSitemap(Tenant $tenant, bool $createBackup): void
    {
        Gate::authorize('manage_seo_files');
        try {
            app(TenantSeoFilePublisher::class)->publishSitemap(
                $tenant,
                Auth::id(),
                TenantSeoFileGeneration::SOURCE_MANUAL,
                $createBackup,
            );
            Notification::make()->title('sitemap.xml опубликован')->success()->send();
            $this->dispatch('$refresh');
        } catch (Throwable $e) {
            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
        }
    }

    private function streamBackupDownload(Tenant $tenant, ?string $relativePath): mixed
    {
        Gate::authorize('manage_seo_files');
        if ($relativePath === null || $relativePath === '') {
            Notification::make()->title('Нет файла')->danger()->send();

            return null;
        }
        $prefix = TenantStorage::for($tenant)->root().'/';
        if (! str_starts_with($relativePath, $prefix)) {
            Notification::make()->title('Некорректный путь')->danger()->send();

            return null;
        }
        $disk = app(SeoFileStorage::class)->disk();
        if (! $disk->exists($relativePath)) {
            Notification::make()->title('Файл не найден')->danger()->send();

            return null;
        }

        return response()->streamDownload(function () use ($disk, $relativePath): void {
            echo $disk->get($relativePath);
        }, basename($relativePath), [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function loadFormState(): array
    {
        $t = currentTenant();
        abort_if($t === null, 403);
        $id = $t->id;

        $allow = TenantSetting::getForTenant($id, 'seo.robots_allow_paths', null);
        $disallow = TenantSetting::getForTenant($id, 'seo.robots_disallow_paths', null);

        return [
            'seo_indexing_enabled' => (bool) TenantSetting::getForTenant($id, 'seo.indexing_enabled', true),
            'seo_sitemap_enabled' => (bool) TenantSetting::getForTenant($id, 'seo.sitemap_enabled', true),
            'seo_custom_robots_enabled' => (bool) TenantSetting::getForTenant($id, 'seo.custom_robots_enabled', false),
            'seo_robots_txt' => (string) TenantSetting::getForTenant($id, 'seo.robots_txt', ''),
            'seo_robots_include_sitemap' => (bool) TenantSetting::getForTenant($id, 'seo.robots_include_sitemap', true),
            'seo_sitemap_stale_after_days' => (int) TenantSetting::getForTenant($id, 'seo.sitemap_stale_after_days', (int) config('seo.sitemap_stale_after_days_default', 7)),
            'seo_sitemap_auto_schedule' => (bool) TenantSetting::getForTenant($id, 'seo.sitemap_auto_regenerate_on_schedule', false),
            'seo_robots_allow_paths_json' => is_array($allow) ? json_encode($allow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '["/"]',
            'seo_robots_disallow_paths_json' => is_array($disallow) ? json_encode($disallow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '["/admin","/api"]',
            'seo_llms_intro' => (string) TenantSetting::getForTenant($id, 'seo.llms_intro', ''),
            'seo_llms_entries_json' => (string) TenantSetting::getForTenant($id, 'seo.llms_entries', ''),
            'seo_route_overrides_json' => (string) TenantSetting::getForTenant($id, 'seo.route_overrides', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateTenantSeoJsonFields(array $data): bool
    {
        $entries = isset($data['seo_llms_entries_json']) ? trim((string) $data['seo_llms_entries_json']) : '';
        if ($entries !== '' && ! $this->isValidJson($entries, $err)) {
            Notification::make()->title('Список URL для llms.txt: невалидный JSON'.($err !== '' ? ' — '.$err : ''))->danger()->send();

            return false;
        }
        if ($entries !== '') {
            $decoded = json_decode($entries, true);
            if (! is_array($decoded) || array_is_list($decoded) === false) {
                Notification::make()->title('Список URL для llms.txt: ожидается JSON-массив [...]')->danger()->send();

                return false;
            }
            foreach ($decoded as $i => $row) {
                if (! is_array($row) || trim((string) ($row['path'] ?? '')) === '') {
                    Notification::make()->title('Список URL для llms.txt: элемент #'.((int) $i + 1).' должен быть объектом с полем path')->danger()->send();

                    return false;
                }
            }
        }

        $routes = isset($data['seo_route_overrides_json']) ? trim((string) $data['seo_route_overrides_json']) : '';
        if ($routes !== '' && ! $this->isValidJson($routes, $err)) {
            Notification::make()->title('Переопределения SEO маршрутов: невалидный JSON'.($err !== '' ? ' — '.$err : ''))->danger()->send();

            return false;
        }
        if ($routes !== '') {
            $decoded = json_decode($routes, true);
            if (! is_array($decoded)) {
                Notification::make()->title('Переопределения SEO маршрутов: ожидается JSON-объект {...}')->danger()->send();

                return false;
            }
        }

        return true;
    }

    private function isValidJson(string $raw, ?string &$error = null): bool
    {
        $error = '';
        json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     *
     * @throws JsonException
     */
    private function decodeJsonArray(string $json, string $label): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new JsonException($label.': ожидался JSON-массив.');
        }
        $out = [];
        foreach ($decoded as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function robotsSnapshotValid(): bool
    {
        $t = currentTenant();
        if ($t === null) {
            return false;
        }

        return app(TenantSeoSnapshotReader::class)->readValid($t->id, TenantSeoFile::TYPE_ROBOTS_TXT) !== null;
    }

    private function sitemapSnapshotValid(): bool
    {
        $t = currentTenant();
        if ($t === null) {
            return false;
        }

        return app(TenantSeoSnapshotReader::class)->readValid($t->id, TenantSeoFile::TYPE_SITEMAP_XML) !== null;
    }

    private function robotsRow(): ?TenantSeoFile
    {
        $t = currentTenant();
        if ($t === null) {
            return null;
        }

        return TenantSeoFile::query()
            ->where('tenant_id', $t->id)
            ->where('type', TenantSeoFile::TYPE_ROBOTS_TXT)
            ->first();
    }

    private function sitemapRow(): ?TenantSeoFile
    {
        $t = currentTenant();
        if ($t === null) {
            return null;
        }

        return TenantSeoFile::query()
            ->where('tenant_id', $t->id)
            ->where('type', TenantSeoFile::TYPE_SITEMAP_XML)
            ->first();
    }

    private function publicRobotsUrl(): string
    {
        $t = currentTenant();
        if ($t === null) {
            return '';
        }

        return app(TenantCanonicalPublicBaseUrl::class)->resolve($t).'/robots.txt';
    }

    private function publicSitemapUrl(): string
    {
        $t = currentTenant();
        if ($t === null) {
            return '';
        }

        return app(TenantCanonicalPublicBaseUrl::class)->resolve($t).'/sitemap.xml';
    }

    private function sitemapFreshnessLabel(): string
    {
        $t = currentTenant();
        if ($t === null) {
            return '—';
        }

        return app(SitemapFreshnessService::class)->resolveStatus($t);
    }

    private function lastPublicContentLabel(): string
    {
        $t = currentTenant();
        if ($t === null) {
            return '—';
        }
        $at = app(PublicContentLastUpdatedService::class)->lastUpdatedAt($t);

        return $at?->format('Y-m-d H:i') ?? '—';
    }
}
