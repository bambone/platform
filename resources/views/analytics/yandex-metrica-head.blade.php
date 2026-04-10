@php
    $cid = (int) $counterId;
    $boolJs = static fn (bool $b): string => $b ? 'true' : 'false';
    $ymInitLines = [
        '        referrer: document.referrer,',
        '        url: location.href,',
        '        clickmap: '.$boolJs($clickmap).',',
        '        trackLinks: '.$boolJs($trackLinks).',',
        '        accurateTrackBounce: '.$boolJs($accurateTrackBounce).',',
    ];
    $webvisorLine = '        webvisor: '.$boolJs($webvisor);
    if ($includeSsr || $includeEcommerceDataLayer) {
        $webvisorLine .= ',';
    }
    $ymInitLines[] = $webvisorLine;
    if ($includeSsr) {
        $ymInitLines[] = '        ssr: true'.($includeEcommerceDataLayer ? ',' : '');
    }
    if ($includeEcommerceDataLayer) {
        $ymInitLines[] = '        ecommerce: "dataLayer"';
    }
    $ymInitCall = '    ym('.$cid.", 'init', {\n".implode("\n", $ymInitLines)."\n    });";
@endphp
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {
            if (document.scripts[j].src === r) { return; }
        }
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js?id={{ $cid }}', 'ym');

    <?php echo $ymInitCall."\n"; ?>
</script>
<!-- /Yandex.Metrika counter -->
