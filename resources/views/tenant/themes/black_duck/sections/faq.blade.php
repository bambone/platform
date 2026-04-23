{{--
  Black Duck: блок FAQ в т.ч. service_faq с data.source = faqs_table_service и data.faq_category = slug (см. expert_auto).
  Главная: вариант bd_home_pre_footer — плотнее к футеру, тонкий градиент-разделитель (без дубля кнопок с футером).
--}}
@php
    $bdFaqPreFooter = tenant()?->themeKey() === 'black_duck'
        && ($page?->slug ?? '') === 'home'
        && ($section->section_key ?? '') === 'faq';
@endphp
@include('tenant.themes.expert_auto.sections.faq', [
    'section' => $section,
    'data' => $data,
    'page' => $page ?? null,
    'sectionVisualVariant' => $bdFaqPreFooter ? 'bd_home_pre_footer' : null,
])
