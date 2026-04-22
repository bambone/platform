@php
    /** @var array<string, mixed> $data */
    $data = is_array($data ?? null) ? $data : [];
    $tenant = tenant();
    if ($tenant === null) {
        return;
    }
    $programs = \App\Models\TenantServiceProgram::forServiceProgramCards((int) $tenant->id, $data);
    if ($programs->isEmpty()) {
        return;
    }
    $ctaCfg = \App\Tenant\Expert\TenantEnrollmentCtaConfig::forCurrent();
    $ctaMode = $ctaCfg?->mode() ?? \App\Tenant\Expert\TenantEnrollmentCtaConfig::MODE_MODAL;
    $enrollmentSlug = trim((string) ($ctaCfg?->enrollmentPageSlug() ?? 'programs')) ?: 'programs';
    $enrollmentUrl = url('/'.$enrollmentSlug);
    $h = trim((string) ($data['section_heading'] ?? ''));
    $sectionLead = array_key_exists('section_lead', $data)
        ? trim((string) $data['section_lead'])
        : 'Модули обучения под конкретную задачу: от городского комфорта до зимней безопасности и спорта.';
    $sid = trim((string) ($data['section_id'] ?? ''));
    $layout = trim((string) ($data['layout'] ?? 'grid'));
    $uniformColumns = filter_var($data['uniform_columns'] ?? false, FILTER_VALIDATE_BOOLEAN);
@endphp
<section @if($sid !== '') id="{{ e($sid) }}" @endif class="expert-programs-mega mb-14 min-w-0 scroll-mt-24 sm:mb-20 sm:scroll-mt-28 lg:mb-28" x-data="{ programsMore: false }">
    <div class="relative overflow-hidden rounded-[1.5rem] border border-white/[0.08] bg-gradient-to-b from-[#0a0d14] to-[#050608] px-3 py-10 shadow-[0_28px_72px_-22px_rgba(0,0,0,0.75)] ring-1 ring-inset ring-white/[0.04] sm:rounded-[2rem] sm:px-8 sm:py-14 lg:px-12 lg:py-20 lg:rounded-[2.5rem]">
        @if($h !== '')
            <div class="relative z-10 mb-8 flex min-w-0 flex-col justify-between gap-4 sm:mb-12 lg:mb-14 lg:flex-row lg:items-end">
                <div class="max-w-3xl min-w-0">
                    <h2 class="expert-section-title text-balance text-[clamp(1.65rem,4vw,3rem)] font-bold tracking-tight text-white/95 leading-[1.12] sm:leading-[1.1]">{{ $h }}</h2>
                    @if($sectionLead !== '')
                        <p class="mt-4 text-[15px] font-normal leading-[1.65] text-silver/85 sm:mt-5 sm:text-lg">{{ $sectionLead }}</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="relative z-10 grid min-w-0 gap-6 sm:gap-8 {{ $layout === 'list' ? 'grid-cols-1' : 'md:grid-cols-2' }} lg:gap-10">
            @foreach($programs as $pi => $program)
                @php
                    $spanFeatured = $program->is_featured && ! $uniformColumns && $layout !== 'list';
                @endphp
                <x-tenant.expert_auto.expert-program-card
                    :program="$program"
                    :tenant="$tenant"
                    forced-picture-mode="auto"
                    :program-index="$pi"
                    :span-featured-in-grid="$spanFeatured"
                    :bind-programs-more="true"
                    :cta-mode="$ctaMode"
                    :enrollment-url="$enrollmentUrl"
                />
            @endforeach
            @if($programs->count() > 3)
                <div class="col-span-full flex justify-center pt-1 md:col-span-2 lg:hidden">
                    <button type="button" class="min-h-11 w-full max-w-sm rounded-full border border-moto-amber/25 bg-moto-amber/[0.08] px-5 py-2 text-sm font-bold text-moto-amber transition hover:bg-moto-amber/15" @click="programsMore = !programsMore" x-text="programsMore ? 'Свернуть список' : 'Смотреть все программы'"></button>
                </div>
            @endif
        </div>
    </div>
</section>
