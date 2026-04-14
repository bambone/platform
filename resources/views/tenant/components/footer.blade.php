{{-- Тема-специфичный футер: данные из view composer (pipeline), без контентного хардкода. --}}
@if (! empty($tenantAdvocateFooter))
    @include('tenant.themes.advocate_editorial.components.site-footer', ['footer' => $tenantAdvocateFooter])
@endif
