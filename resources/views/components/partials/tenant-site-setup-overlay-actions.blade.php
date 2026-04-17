{{--
  Требует те же переменные, что и родительский tenant-site-setup-overlay:
  $actionUrl, $canSnooze, $canNotNeeded, $primaryNav, $targetUrl, $canCompleteHere, $primaryIsSkipStep
--}}
<div class="fi-ts-setup-actions-row">
    <div class="fi-ts-setup-actions-primary">
        @if($primaryNav && $targetUrl !== '')
            <a
                href="{{ $targetUrl }}"
                class="fi-ts-setup-btn fi-ts-setup-btn-primary inline-flex items-center justify-center no-underline"
            >
                <span class="fi-ts-setup-btn-label">Перейти к шагу</span>
            </a>
        @elseif($actionUrl && $canCompleteHere)
            <form method="post" action="{{ $actionUrl }}" class="inline">
                @csrf
                <input type="hidden" name="action" value="next" />
                <button type="submit" class="fi-ts-setup-btn fi-ts-setup-btn-primary">
                    <span class="fi-ts-setup-btn-label">Дальше</span>
                </button>
            </form>
        @elseif($actionUrl && ! $canCompleteHere && ! $primaryNav)
            <form method="post" action="{{ $actionUrl }}" class="inline">
                @csrf
                <input type="hidden" name="action" value="next" />
                <button type="submit" class="fi-ts-setup-btn fi-ts-setup-btn-primary">
                    <span class="fi-ts-setup-btn-label">Пропустить шаг</span>
                </button>
            </form>
        @endif
    </div>

    @if($actionUrl)
        <div class="fi-ts-setup-actions-tertiary" role="group" aria-label="Дополнительные действия по шагу">
            @if(! $primaryIsSkipStep)
                <form method="post" action="{{ $actionUrl }}" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="snooze" />
                    <button
                        type="submit"
                        @disabled(! $canSnooze)
                        class="fi-ts-setup-text-btn {{ $canSnooze ? '' : 'fi-ts-setup-text-btn-disabled' }}"
                        aria-label="Отложить шаг"
                    >
                        Позже
                    </button>
                </form>
            @endif
            <form method="post" action="{{ $actionUrl }}" class="inline">
                @csrf
                <input type="hidden" name="action" value="not_needed" />
                <button
                    type="submit"
                    @disabled(! $canNotNeeded)
                    class="fi-ts-setup-text-btn {{ $canNotNeeded ? '' : 'fi-ts-setup-text-btn-disabled' }}"
                    aria-label="Не требуется для проекта"
                >
                    Не требуется
                </button>
            </form>
            <form method="post" action="{{ $actionUrl }}" class="inline">
                @csrf
                <input type="hidden" name="action" value="pause" />
                <button type="submit" class="fi-ts-setup-text-btn">
                    Пауза
                </button>
            </form>
        </div>
    @endif

    <div class="fi-ts-setup-actions-overview">
        <a href="{{ \App\Filament\Tenant\Pages\TenantSiteSetupCenterPage::getUrl() }}" class="fi-ts-setup-overview-link">
            Обзор запуска
        </a>
    </div>
</div>
