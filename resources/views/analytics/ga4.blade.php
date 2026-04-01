{{-- Platform-controlled GA4 snippet only; measurementId is validated G-XXXXXXXX. --}}
<script async src="https://www.googletagmanager.com/gtag/js?id={{ e($measurementId) }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ e($measurementId) }}');
</script>
