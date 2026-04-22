@php
    /** @var \App\Filament\Platform\Pages\PlatformDevScriptsPage $this */
    $scripts = $this->scripts;
    $last = $this->lastRun;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Предупреждение: те же отступы, что у секций Filament --}}
        <div
            class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-600/15 dark:bg-amber-500/10 dark:text-amber-50 dark:ring-amber-400/25"
        >
            <p class="font-semibold leading-6 text-amber-950 dark:text-amber-100">
                Только среда <code class="rounded-md bg-amber-100/80 px-1.5 py-0.5 text-xs font-normal dark:bg-amber-950/60">local</code>
            </p>
            <p class="mt-1.5 max-w-4xl leading-relaxed text-amber-900/90 dark:text-amber-100/85">
                Нужны роль платформы и <code class="text-xs">APP_ENV=local</code>. Долгие задачи идут в одном запросе — не закрывайте вкладку.
                У процесса PHP в PATH должны быть утилиты для сценариев восстановления БД:
                <code class="text-xs">mysql</code>,
                <code class="text-xs">mysqldump</code>,
                <code class="text-xs">zstd</code>,
                <code class="text-xs">rclone</code>
                (как в обычном терминале).
            </p>
        </div>

        <div>
            {{ $this->form }}
        </div>

        <x-filament::section
            collapsible
            collapsed
            persist-collapsed
            collapse-id="dev-scripts-env"
        >
            <x-slot name="heading">Что прописать в .env для сценариев</x-slot>
            <x-slot name="description">
                <code class="text-xs">MAIL_*</code> и пароли почты к восстановлению БД и R2 не относятся.
            </x-slot>

            <div class="prose prose-sm max-w-none dark:prose-invert">
                <ul class="!mt-0 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                    <li>
                        <strong class="text-gray-950 dark:text-white">Обязательно для страницы:</strong>
                        <code class="text-xs">APP_ENV=local</code>
                    </li>
                    <li>
                        <strong class="text-gray-950 dark:text-white">База (и скрипты, и artisan):</strong>
                        <code class="text-xs">DB_HOST</code> (локально: <code class="text-xs">127.0.0.1</code> или, например, <code class="text-xs">127.127.126.3</code> в OSPanel — допустимо как 127.0.0.0/8),
                        <code class="text-xs">DB_PORT</code>,
                        <code class="text-xs">DB_DATABASE</code>,
                        <code class="text-xs">DB_USERNAME</code>,
                        <code class="text-xs">DB_PASSWORD</code>
                    </li>
                    <li>
                        <strong class="text-gray-950 dark:text-white">Скачивание бэкапа через rclone:</strong>
                        <code class="text-xs">RENTBASE_RESTORE_RCLONE_REMOTE</code>
                        (например <code class="text-xs">mailru-webdav:Backups/rentbase/mysql</code>),
                        опционально <code class="text-xs">RCLONE_CONFIG</code> — путь к файлу конфигурации rclone
                    </li>
                    <li>
                        <strong class="text-gray-950 dark:text-white">Если нет mysql / rclone / zstd в PATH (OSPanel, Cursor, PHP):</strong>
                        <code class="text-xs">RENTBASE_PATH_PREPEND</code>
                        — каталоги через <code class="text-xs">;</code>, например
                        <code class="text-xs break-all">C:\OSPanel\modules\MySQL-8.4\bin;C:\Program Files\rclone;C:\Program Files\zstd</code>
                    </li>
                    <li>
                        <strong class="text-gray-950 dark:text-white">Из терминала для restore</strong> (на UI для части сценариев уже подставляется):
                        <code class="text-xs">CONFIRM_STAGE_RESTORE=yes</code>
                    </li>
                    <li>
                        <strong class="text-gray-950 dark:text-white">Локальное зеркало медиа:</strong>
                        <code class="text-xs">MEDIA_LOCAL_ROOT</code>
                        — или укажите путь в форме выше
                    </li>
                    <li>
                        <strong class="text-gray-950 dark:text-white">Backfill из R2 (S3 API):</strong>
                        как в <code class="text-xs">.env.example</code> —
                        <code class="text-xs">AWS_ACCESS_KEY_ID</code>,
                        <code class="text-xs">AWS_SECRET_ACCESS_KEY</code>,
                        <code class="text-xs">AWS_ENDPOINT</code>,
                        <code class="text-xs">R2_PUBLIC_BUCKET</code>
                        и связанные переменные диска <code class="text-xs">r2-public</code>
                    </li>
                </ul>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Сценарии</x-slot>
            <x-slot name="description">Запуск из корня проекта. Ниже — вывод и код завершения последнего запуска.</x-slot>

            <div class="grid gap-3" wire:loading.class="pointer-events-none opacity-60" wire:target="runScript">
                @foreach ($scripts as $script)
                    @php
                        $devScriptIdJs = (string) \Illuminate\Support\Js::from($script['id']);
                        $devScriptConfirmJs = (string) \Illuminate\Support\Js::from($script['confirm_message'] ?? 'Запустить этот сценарий?');
                        $devScriptClickWithConfirm = 'if (! confirm('.$devScriptConfirmJs.')) return; $wire.runScript('.$devScriptIdJs.')';
                    @endphp
                    <div
                        class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $script['label'] }}
                            </p>
                            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                {{ $script['description'] }}
                            </p>
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-500">
                                <code
                                    class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-gray-800 dark:bg-gray-950 dark:text-gray-200"
                                >{{ $script['id'] }}</code>
                                @if (isset($script['timeout']))
                                    <span class="text-gray-400 dark:text-gray-500">·</span>
                                    <span>таймаут {{ (int) $script['timeout'] }} с</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 justify-end sm:pl-4">
                            @if (! empty($script['requires_confirm']))
                                {{-- Нативный confirm + $wire (wire:confirm даёт SyntaxError в evaluateRaw) --}}
                                <x-filament::button
                                    type="button"
                                    color="primary"
                                    size="md"
                                    :loading-indicator="false"
                                    x-on:click.prevent='{!! $devScriptClickWithConfirm !!}'
                                    wire:loading.attr="disabled"
                                >
                                    <span wire:loading.remove>Запустить</span>
                                    <span wire:loading class="inline-flex items-center gap-2">
                                        <x-filament::loading-indicator class="h-5 w-5" />
                                        Выполняется…
                                    </span>
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    type="button"
                                    color="primary"
                                    size="md"
                                    :loading-indicator="false"
                                    wire:click='runScript({!! $devScriptIdJs !!})'
                                    wire:loading.attr="disabled"
                                >
                                    <span wire:loading.remove>Запустить</span>
                                    <span wire:loading class="inline-flex items-center gap-2">
                                        <x-filament::loading-indicator class="h-5 w-5" />
                                        Выполняется…
                                    </span>
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        @if (is_array($last))
            <x-filament::section>
                <x-slot name="heading">Последний результат</x-slot>
                <x-slot name="description">
                    @if (! empty($last['finished_at']))
                        {{ $last['finished_at'] }}
                    @endif
                </x-slot>

                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        @if (! empty($last['success']))
                            <x-filament::badge color="success">Успех</x-filament::badge>
                        @else
                            <x-filament::badge color="danger">Ошибка</x-filament::badge>
                        @endif
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            Код выхода:
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $last['exit_code'] ?? '—' }}</span>
                        </span>
                        @if (! empty($last['label']))
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $last['label'] }}</span>
                        @endif
                    </div>
                    <pre
                        class="max-h-[min(32rem,70vh)] overflow-auto rounded-lg border border-gray-200 bg-gray-950 p-4 font-mono text-xs leading-relaxed text-gray-100 dark:border-white/10 dark:bg-gray-950"
                    >{{ $last['output'] ?? '' }}</pre>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
