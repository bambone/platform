{{-- Справка для клиентов: Google Analytics 4 — только Measurement ID. --}}
<div class="space-y-5 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
    <p class="rounded-lg border border-amber-500/35 bg-amber-500/10 px-3 py-2 text-amber-950 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
        Нужен <strong>только Measurement ID</strong> в формате <code class="rounded bg-black/5 px-1 py-0.5 text-xs dark:bg-white/10">G-XXXXXXXXXX</code>. Не вставляйте полный фрагмент gtag.js из интерфейса Google — платформа подключает загрузку gtag сама.
    </p>

    <div>
        <h3 class="mb-2 text-base font-semibold text-gray-950 dark:text-white">Где найти Measurement ID в Google Analytics 4</h3>
        <ol class="list-decimal space-y-2 pl-5">
            <li>Откройте <a href="https://analytics.google.com" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline decoration-primary-600/40 hover:decoration-primary-600 dark:text-primary-400">analytics.google.com</a> и выберите нужный <strong>аккаунт</strong> и <strong>ресурс GA4</strong> (Property).</li>
            <li>В левом нижнем углу нажмите иконку <strong>«Администратор»</strong> (шестерёнка).</li>
            <li>В центральной колонке «Ресурс» откройте <strong>«Потоки данных»</strong> (Data streams).</li>
            <li>Выберите поток типа <strong>«Веб»</strong> для вашего сайта (если потока нет — создайте его, указав URL сайта).</li>
            <li>На странице потока вверху будет <strong>Measurement ID</strong> — строка вида <code class="rounded bg-black/5 px-1 py-0.5 text-xs dark:bg-white/10">G-ABC123DEF4</code>. Скопируйте её целиком.</li>
            <li>Вставьте ID в поле «Measurement ID (GA4)» в настройках сайта и включите переключатель «Google Analytics 4».</li>
        </ol>
    </div>

    <div>
        <h3 class="mb-2 text-base font-semibold text-gray-950 dark:text-white">Если видите только «Идентификатор потока»</h3>
        <p>
            У веб‑потока GA4 есть числовой идентификатор потока и отдельно <strong>Measurement ID</strong> с префиксом <code class="rounded bg-black/5 px-1 py-0.5 text-xs dark:bg-white/10">G-</code>. Для настроек RentBase нужен именно <strong>G-…</strong> — он показан на карточке веб‑потока в разделе «Потоки данных».
        </p>
    </div>
</div>
