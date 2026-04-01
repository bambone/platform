{{-- Справка для клиентов: подключение Яндекс Метрики (только ID, без вставки кода). --}}
<div class="space-y-5 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
    <p class="rounded-lg border border-amber-500/35 bg-amber-500/10 px-3 py-2 text-amber-950 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
        Сюда нужен <strong>только номер счётчика</strong> (цифры). Не вставляйте код счётчика из Метрики целиком — платформа подключает официальный <code class="rounded bg-black/5 px-1 py-0.5 text-xs dark:bg-white/10">tag.js</code> сама.
    </p>

    <div>
        <h3 class="mb-2 text-base font-semibold text-gray-950 dark:text-white">Где взять ID счётчика</h3>
        <ol class="list-decimal space-y-2 pl-5">
            <li>Откройте <a href="https://metrika.yandex.ru" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline decoration-primary-600/40 hover:decoration-primary-600 dark:text-primary-400">metrika.yandex.ru</a> и войдите в аккаунт Яндекса.</li>
            <li>Если счётчика ещё нет — нажмите <strong>«Добавить счётчик»</strong>, укажите адрес вашего сайта и создайте счётчик.</li>
            <li>Откройте нужный счётчик. Номер счётчика (только цифры, например <code class="rounded bg-black/5 px-1 py-0.5 text-xs dark:bg-white/10">12345678</code>) обычно виден в шапке рядом с названием или в разделе <strong>«Настройка»</strong> → блок про код счётчика.</li>
            <li>Скопируйте <strong>только этот номер</strong> в поле «ID счётчика Метрики» в настройках сайта и включите переключатель «Яндекс Метрика».</li>
        </ol>
    </div>

    <div>
        <h3 class="mb-2 text-base font-semibold text-gray-950 dark:text-white">Переключатели ниже — что они делают</h3>
        <p class="mb-2 text-gray-600 dark:text-gray-400">
            Они задают параметры инициализации счётчика на вашем сайте (как в официальной настройке <code class="rounded bg-black/5 px-1 py-0.5 text-xs dark:bg-white/10">ym(..., &quot;init&quot;, {...})</code>).
        </p>
        <ul class="list-disc space-y-2 pl-5">
            <li><strong>Вебвизор</strong> — передача данных для записи сессий. В кабинете Метрики для полноценной работы вебвизора обычно нужно также включить соответствующие опции в настройках счётчика (раздел настроек счётчика / вебвизор).</li>
            <li><strong>Карта кликов</strong> — сбор данных для карты кликов.</li>
            <li><strong>Отслеживание ссылок</strong> — учёт переходов по внешним ссылкам.</li>
            <li><strong>Точный показатель отказов</strong> — учёт времени на странице для расчёта отказов по правилам Метрики.</li>
        </ul>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
            Если какая‑то функция в отчётах Метрики «не завелась», проверьте, что опция включена и здесь, и при необходимости в настройках счётчика в интерфейсе Яндекса.
        </p>
    </div>
</div>
