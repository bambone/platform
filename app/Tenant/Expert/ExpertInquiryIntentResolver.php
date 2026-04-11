<?php

namespace App\Tenant\Expert;

/**
 * Derives intent_tags for expert_service_inquiry payload from program + free text.
 */
final class ExpertInquiryIntentResolver
{
    /**
     * @return list<string>
     */
    public function resolve(?string $programSlug, string $goalText): array
    {
        $tags = [];
        $slug = $programSlug !== null ? trim($programSlug) : '';
        if ($slug !== '') {
            foreach ($this->tagsForProgramSlug($slug) as $t) {
                $tags[] = $t;
            }
        }

        $lower = mb_strtolower($goalText);
        $map = [
            'парковк' => 'parking',
            'задний ход' => 'parking',
            'город' => 'city-driving',
            'поток' => 'city-driving',
            'перестроен' => 'city-driving',
            'зим' => 'winter-driving',
            'гололёд' => 'winter-driving',
            'контравар' => 'counter-emergency',
            'занос' => 'counter-emergency',
            'маршрут' => 'route-practice',
            'дорог' => 'route-practice',
            'страх' => 'confidence',
            'уверен' => 'confidence',
            'спорт' => 'motorsport',
            'тайм-аттак' => 'motorsport',
            'соревнован' => 'motorsport',
        ];
        foreach ($map as $needle => $tag) {
            if (str_contains($lower, $needle)) {
                $tags[] = $tag;
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @return list<string>
     */
    private function tagsForProgramSlug(string $slug): array
    {
        return match ($slug) {
            'parking' => ['parking'],
            'city-driving' => ['city-driving'],
            'counter-emergency' => ['winter-driving', 'counter-emergency'],
            'route' => ['route-practice'],
            'confidence' => ['confidence'],
            'motorsport' => ['motorsport'],
            'single-session' => [],
            default => [],
        };
    }
}
