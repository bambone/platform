<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

/**
 * PHP-реестр услуг и бизнес-групп (v1: код, не БД). Источник для refresh / hub / посадочные.
 *
 * @phpstan-type IncludedItem array{title: string, text: string}
 * @phpstan-type ServiceRow array{
 *   slug: string,
 *   title: string,
 *   short_title: string,
 *   blurb: string,
 *   booking_mode: string,
 *   has_landing: bool,
 *   group_key: string,
 *   group_title: string,
 *   group_blurb: string,
 *   group_sort: int,
 *   service_sort: int,
 *   show_on_home: bool,
 *   show_in_catalog: bool,
 *   is_featured: bool,
 *   body_intro: string,
 *   included_items: list<IncludedItem>
 * }
 */
final class BlackDuckServiceRegistry
{
    /**
     * Минимальное количество элементов в сетке works_portfolio для приёмки (согласовано с планом).
     */
    public const MIN_WORKS_PORTFOLIO_ITEMS_ACCEPTANCE = 12;

    /**
     * @return list<ServiceRow>
     */
    public static function all(): array
    {
        return self::rowsInner();
    }

    /**
     * Совместимость с {@see BlackDuckContentConstants::serviceMatrixQ1()}.
     *
     * @return list<array{slug: string, title: string, blurb: string, booking_mode: string, has_landing: bool}>
     */
    public static function legacyMatrixQ1(): array
    {
        $out = [];
        foreach (self::rowsInner() as $r) {
            $out[] = [
                'slug' => $r['slug'],
                'title' => $r['title'],
                'blurb' => $r['blurb'],
                'booking_mode' => $r['booking_mode'],
                'has_landing' => $r['has_landing'],
            ];
        }

        return $out;
    }

    public static function rowBySlug(string $slug): ?array
    {
        foreach (self::rowsInner() as $r) {
            if ($r['slug'] === $slug) {
                return $r;
            }
        }

        return null;
    }

    /**
     * @return list<array{group_key: string, group_title: string, group_blurb: string, group_sort: int, items: list<array<string, mixed>>}>
     */
    public static function catalogGroupsWithPlaceholderItems(): array
    {
        $byGroup = [];
        foreach (self::rowsInner() as $r) {
            if (! $r['show_in_catalog']) {
                continue;
            }
            $gk = $r['group_key'];
            if (! isset($byGroup[$gk])) {
                $byGroup[$gk] = [
                    'group_key' => $gk,
                    'group_title' => $r['group_title'],
                    'group_blurb' => $r['group_blurb'],
                    'group_sort' => $r['group_sort'],
                    'items' => [],
                ];
            }
            $byGroup[$gk]['items'][] = [
                'slug' => $r['slug'],
                'title' => $r['title'],
                'short_title' => $r['short_title'],
                'blurb' => $r['blurb'],
                'booking_mode' => $r['booking_mode'],
                'is_featured' => $r['is_featured'],
                'service_sort' => $r['service_sort'],
            ];
        }
        uasort($byGroup, fn (array $a, array $b): int => $a['group_sort'] <=> $b['group_sort']);
        $out = array_values($byGroup);
        foreach ($out as &$g) {
            usort(
                $g['items'],
                fn (array $a, array $b): int => ((int) ($a['service_sort'] ?? 0)) <=> ((int) ($b['service_sort'] ?? 0)),
            );
        }
        unset($g);

        return $out;
    }

    public static function serviceTitleForSlug(string $slug): string
    {
        $r = self::rowBySlug($slug);

        return $r !== null ? (string) $r['title'] : $slug;
    }

    /**
     * @return list<ServiceRow>
     */
    private static function rowsInner(): array
    {
        $g1 = 'bystryie';
        $g1t = 'Быстрые и регулярные услуги';
        $g1b = 'Короткие работы, часть из которых доступна онлайн при включённом расписании.';

        $g2 = 'zashchita-kuzova';
        $g2t = 'Защита кузова';
        $g2b = 'Плёнка, керамика, бронирование — по осмотру и согласованной зоне.';

        $g3 = 'vosstanovlenie-i-ukhod';
        $g3t = 'Восстановление и уход';
        $g3b = 'Полировка, химчистка, кожа и подготовка под дальнейший план.';

        $g4 = 'komfort';
        $g4t = 'Комфорт и дооснащение';
        $g4b = 'Снижение шума и доработки по диагностике.';

        $g5 = 'slozhnye';
        $g5t = 'Сложные и проектные работы';
        $g5b = 'Расчёт срока и объёма до старта; винил — по заявке.';

        return [
            self::r('detejling-mojka', 'Детейлинг-мойка', 'Мойка', 'Короткие слоты онлайн после настройки расписания.', 'instant', true, $g1, $g1t, $g1b, 1, 10, true, true, true, self::p('Детергент, контактная, подсушка, базовая защитная сушка. Длительность зависит от класса кузова и пакета: уточняет мастер на приёме. Результат — чистая геометрия, без визуального «мыла» в стыках, если пакет это предусматривает.', self::inclMoyka())),

            self::r('antidozhd', 'Антидождь (гидрофоб)', 'Антидождь', 'Обработка стекол для дождливой погоды; уточнить составы у мастера.', 'confirm', true, $g1, $g1t, $g1b, 1, 20, false, true, false, self::p('Покрытия для стёкол с гидрофобным эффектом. Слои, составы и срок пересмотра согласуем до работ и фиксируем по факту смены. Не влияет на визуал тонировки, если плёнка уже есть — порядок слоёв согласуем.', self::inclStekla1())),

            self::r('tonirovka', 'Тонировка / оптика', 'Тонировка', 'Стёкла и оптика, варианты плёнки — по согласованию.', 'confirm', true, $g1, $g1t, $g1b, 1, 30, true, true, true, self::p('Подбор плотности, проверка по регламенту ГИБДД при необходимости, оклейка с контролем кромок и пыльной фазы. Оптика — бронеплёнка/тон с отдельной оценкой.', self::inclTon1())),

            self::r('setki-radiatora', 'Установка защитных сеток', 'Сетки', 'Защита радиатора сетками: подбор и монтаж под модель.', 'confirm', true, $g1, $g1t, $g1b, 1, 40, false, true, false, self::p('Сетки под геометрию кузова, крепёж и проверка зазоров, чтобы не было вибрации. Сроки — после согласования поставки и осмотра.', self::inclSimpl())),

            self::r('remont-skolov', 'Ремонт сколов', 'Сколы', 'Точечный ремонт ЛКП по дефекту, без «лишнего» кузова в работу.', 'confirm', true, $g1, $g1t, $g1b, 1, 50, false, true, false, self::p('Вход по дефекту: оценка глубины, локальный ремонт, полировка зоны, контроль бликов. Не делаем «космос» вместо точечного ремонта — цель: аккуратно на месте.', self::inclSimpl())),

            self::r('ppf', 'Антигравий PPF', 'PPF', 'Защита зон кузова, макет и зоны — по осмотру.', 'confirm', true, $g2, $g2t, $g2b, 2, 10, true, true, true, self::p('Полиуретан: зона риска и кромка под ракурс, подбор толщины, разметка, контроль стыков. Сроки — по площади и плану смен, без сюрпризов «по ходу».', self::inclPpf())),

            self::r('keramika', 'Керамика кузова', 'Керамика', 'Серия этапов, согласование плана с мастером.', 'confirm', true, $g2, $g2t, $g2b, 2, 20, true, true, true, self::p('Серия этапов, препа и финиш, контроль гидрофоба в зоне работ, рекомендации по мойкам. График согласуем, чтобы не сушить вне очереди.', self::inclKera())),

            self::r('bronirovanie-salona', 'Бронь элементов салона', 'Салон: бронь', 'Защитные плёнки на пластик, экран, пороги — по плану.', 'confirm', true, $g2, $g2t, $g2b, 2, 30, false, true, false, self::p('Плёнки и наборы на пластик, дисплеи, пороги. Приоритезация вместе с вами, чтобы бюджет ушёл в зоны, которые реально бьют обувью и рукой.', self::inclSimpl())),

            self::r('polirovka-kuzova', 'Полировка кузова', 'Полировка', 'По состоянию ЛКП, абразив и финиш по задаче.', 'confirm', true, $g3, $g3t, $g3b, 3, 10, true, true, true, self::p('По состоянию ЛКП: абразив, контроль толщины, финиш, при необходимости защитный сценарий — отдельно. Оценим риск перегрева и остатка дефектов до старта.', self::inclPolish())),

            self::r('himchistka-salona', 'Химчистка салона', 'Химчистка салона', 'Салон, кожа, текстиль — объём по осмотру.', 'confirm', true, $g3, $g3t, $g3b, 3, 20, true, true, true, self::p('Салон, кожа, текстиль. Глубина и составы — после осмотра и теста материалов. Сроки и проветривание согласуем, чтобы не «жить в химии» после сдачи.', self::inclHim1())),

            self::r('himchistka-kuzova', 'Химчистка кузова', 'Химчистка кузова', 'Деинкрустация, обезжиривание, подготовка под LKP.', 'confirm', true, $g3, $g3t, $g3b, 3, 30, false, true, false, self::p('Внешний хим-чек: снятие битумных и дорожных плёнок, подготовка к полировке/керамике/плёнке. Без «перемыва» площадей, которые в работу не берём.', self::inclSimpl())),

            self::r('himchistka-diskov', 'Химчистка дисков', 'Диски', 'Очистка суппорт-зон и внутренней плоскости по доступу.', 'confirm', true, $g3, $g3t, $g3b, 3, 40, false, true, false, self::p('Снятие грязи, калипер и спицы в доступе. Бережно к ЛКП диска: без диска нельзя рисковать сырой площадью', self::inclSimpl())),

            self::r('podkapotnaya-himchistka', 'Подкапотное: чистка и консервация', 'Подкапот', 'Сухая и мойка-зонально; консервация пластиков и кожухов по задаче.', 'confirm', true, $g3, $g3t, $g3b, 3, 50, false, true, false, self::p('Сухая/мокрая схема, маркировка снятых кожухов, консервация пластиков. Работы вне ДВС в целом — по чек-листу, без вскрытия агрегатов, если нет в задаче.', self::inclSimpl())),

            self::r('kozha-keramika', 'Кожа: керамика салона', 'Кожа: керамика', 'Пропитка/керамика кожи и фактуры — по тесту материалов.', 'confirm', true, $g3, $g3t, $g3b, 3, 60, false, true, false, self::p('Пропитка/керамика кожи после теста и чистки. Важен совместимый состав под фактуру — иначе глянец вместо гигиены.', self::inclKozh1())),

            self::r('restavratsiya-kozhi', 'Реставрация кожи', 'Реставрация кожи', 'Износ, потёртости, цвет — работа по согласованному ТЗ.', 'confirm', true, $g3, $g3t, $g3b, 3, 70, false, true, false, self::p('Пигмент, лак, шов — план по осмотру. Циклы сушек и сдача без липкости и пятен на приоритетных сиденьях.', self::inclKozh2())),

            self::r('shumka', 'Шумоизоляция', 'Шумоизоляция', 'Объём и сроки после диагностики.', 'confirm', true, $g4, $g4t, $g4b, 4, 10, false, true, false, self::p('План и стоимость после разборки/замера шума. Не делаем «толстый слой везде» — только по каналу шума и договорённости.', self::inclSimpl())),

            self::r('pdr', 'PDR (без покраса)', 'PDR', 'Вмятины без окраса — оценка на месте.', 'confirm', true, $g5, $g5t, $g5b, 5, 10, true, true, true, self::p('Доступ к вмятине, инструмент, иногда съём обшивок — обсуждаем до клея. Не вся вмятина тянется: честно скажем до работ.', self::inclPdr())),

            self::r('predprodazhnaya', 'Предпродажная подготовка', 'Предпродажа', 'Комплекс под продажу по чек-листу.', 'confirm', true, $g5, $g5t, $g5b, 5, 20, true, true, true, self::p('Мойка, косметика, мелкие косяки по списку — чтобы покупателю было за что схватить глаз. Бюджет фиксируем до кузова, без раздувания.', self::inclSimpl())),

            self::r('#expert-inquiry', 'Виниловая оклейка', 'Винил', 'Проекты по дизайну; расчёт и сроки — заявка.', 'quote', false, $g5, $g5t, $g5b, 5, 30, false, true, false, self::p('Проекты обклейки: макет, плёнка, согласование кромок и срока. Сложные работы — только заявка и расчёт, без мгновенного слота.', self::inclSimpl())),
        ];
    }

    /**
     * @param  list<IncludedItem>  $included
     * @return array{body_intro: string, included_items: list<IncludedItem>}
     */
    private static function p(string $bodyIntro, array $included): array
    {
        return [
            'body_intro' => $bodyIntro,
            'included_items' => $included,
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclMoyka(): array
    {
        return [
            ['title' => 'Осмотр и выбор пакета', 'text' => 'Согласование площадей, составов, времени.'],
            ['title' => 'Предмойка и контактная', 'text' => 'Снятие пыли, обезжиривание по типу кузова.'],
            ['title' => 'Сушка и отдача', 'text' => 'Короткий осмотр с вами, рекомендации по краткому уходу.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclStekla1(): array
    {
        return [
            ['title' => 'Подготовка стекла', 'text' => 'Очистка, обезжиривание, контроль царапин.'],
            ['title' => 'Нанесение', 'text' => 'Слой и полимер — по согласованной схеме.'],
            ['title' => 'Контроль и рекомендации', 'text' => 'Сроки повторения и мойка без агрессии в первую неделю.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclTon1(): array
    {
        return [
            ['title' => 'Согласование', 'text' => 'Плотность, зоны, оптика.'],
            ['title' => 'Оклейка', 'text' => 'Снятие пыли, формовка, кромка, отлов пузырей.'],
            ['title' => 'Сдача', 'text' => 'Сушка, осмотр, рекомендации по кварцеванию.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclPpf(): array
    {
        return [
            ['title' => 'Осмотр и план', 'text' => 'Макет зон, согласование с вами, при необходимости суточка плёнки.'],
            ['title' => 'Подготовка ЛКП', 'text' => 'Посторонняя, заклейка рисков, разметка.'],
            ['title' => 'Оклейка и сдача', 'text' => 'Контроль кромок, стыков, отчёт по зоне.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclKera(): array
    {
        return [
            ['title' => 'Подготовка', 'text' => 'Снятия и абразив по состоянию, обезжиривание.'],
            ['title' => 'Серия этапов', 'text' => 'База, слои, сушки согласно плану.'],
            ['title' => 'Сдача', 'text' => 'Проверка капли, рекомендации по мойкам.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclPolish(): array
    {
        return [
            ['title' => 'Осмотр ЛКП', 'text' => 'Глубина дефектов, риск по толщине.'],
            ['title' => 'Абразив и финиш', 'text' => 'По согласованной зоне, без гонок за бликом.'],
            ['title' => 'Итог', 'text' => 'Снятие силикона, сдача с подсветкой.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclHim1(): array
    {
        return [
            ['title' => 'Осмотр', 'text' => 'Пятна, запах, материалы, что выделяем в работу.'],
            ['title' => 'Чистка', 'text' => 'Составы по тесту, вентиляция смены.'],
            ['title' => 'Сдача', 'text' => 'Сушка сидений, краткие рекомендации.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclKozh1(): array
    {
        return [
            ['title' => 'Очистка', 'text' => 'Снятие грязи, тест пигмента.'],
            ['title' => 'Керамика/пропитка', 'text' => 'Слой по согласованной схеме.'],
            ['title' => 'Сдача', 'text' => 'Сухость, касание швов, рекомендации.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclKozh2(): array
    {
        return [
            ['title' => 'ТЗ и цвет', 'text' => 'Согласование пятен и зон, подбор.'],
            ['title' => 'Ремонт', 'text' => 'Шпаклёвка/левелер, пигмент, сушка.'],
            ['title' => 'Лак/финиш', 'text' => 'Мат/глянец, тактильная проверка.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclPdr(): array
    {
        return [
            ['title' => 'Осмотр', 'text' => 'Доступ, клей, задний свет, прогноз.'],
            ['title' => 'PDR', 'text' => 'Контроль кромки, без тресков.'],
            ['title' => 'Сдача', 'text' => 'Проверка с вами, при необходимости полировка точки.'],
        ];
    }

    /**
     * @return list<IncludedItem>
     */
    private static function inclSimpl(): array
    {
        return [
            ['title' => 'Согласование', 'text' => 'Объём, срок, цена вилкой до углубления.'],
            ['title' => 'Работы', 'text' => 'По плану смены, фото-отчёт при согласовании.'],
            ['title' => 'Сдача', 'text' => 'Проверка с вами, чек-лист.'],
        ];
    }

    /**
     * @param  list<IncludedItem>  $incl
     * @return array<string, mixed>
     */
    private static function r(
        string $slug,
        string $title,
        string $shortTitle,
        string $blurb,
        string $bookingMode,
        bool $hasLanding,
        string $groupKey,
        string $groupTitle,
        string $groupBlurb,
        int $groupSort,
        int $serviceSort,
        bool $showOnHome,
        bool $showInCatalog,
        bool $isFeatured,
        array $texts,
    ): array {
        return [
            'slug' => $slug,
            'title' => $title,
            'short_title' => $shortTitle,
            'blurb' => $blurb,
            'booking_mode' => $bookingMode,
            'has_landing' => $hasLanding,
            'group_key' => $groupKey,
            'group_title' => $groupTitle,
            'group_blurb' => $groupBlurb,
            'group_sort' => $groupSort,
            'service_sort' => $serviceSort,
            'show_on_home' => $showOnHome,
            'show_in_catalog' => $showInCatalog,
            'is_featured' => $isFeatured,
            'body_intro' => (string) $texts['body_intro'],
            'included_items' => $texts['included_items'] ?? [],
        ];
    }
}
