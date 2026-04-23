<?php

declare(strict_types=1);

namespace App\Services\PublicSite;

use App\Models\PageSection;
use App\Models\Tenant;
use App\Tenant\BlackDuck\BlackDuckServiceRegistry;
use Illuminate\Http\Request;

/**
 * Данные для partial contact-inquiry-form: опции услуг и prefill для Black Duck.
 */
final class ContactInquiryFormPresenter
{
    public function __construct(
        private Request $request,
    ) {}

    /**
     * Ключ в data_json; если отсутствует в старой БД, для black_duck считаем true (как в сиде), иначе новые витрины остаются без требования.
     */
    public static function sectionRequiresServiceSelector(array $data, ?Tenant $tenant): bool
    {
        if (array_key_exists('requires_service_selector', $data)) {
            return (bool) $data['requires_service_selector'];
        }

        return $tenant?->theme_key === 'black_duck';
    }

    /**
     * @return array{
     *     requires_service_selector: bool,
     *     service_options: list<array{slug: string, title: string}>,
     *     prefilled_service_slug: string,
     *     prefill_message: string,
     *     show_service_field: bool
     * }
     */
    public function present(PageSection $section, array $data, ?Tenant $tenant): array
    {
        $requires = self::sectionRequiresServiceSelector($data, $tenant);
        $prefillMessage = trim((string) ($data['prefill_message'] ?? ''));
        $isBlackDuck = $tenant?->theme_key === 'black_duck';
        $options = ($isBlackDuck && $requires)
            ? BlackDuckServiceRegistry::inquiryFormLandingOptions()
            : [];
        $prefilledServiceSlug = '';
        if ($requires && $isBlackDuck && $options !== []) {
            $q = trim((string) $this->request->query('service', ''));
            if ($q !== '' && BlackDuckServiceRegistry::rowBySlug($q) !== null) {
                $prefilledServiceSlug = $q;
            }
        }
        if ($prefillMessage === '' && $prefilledServiceSlug !== '') {
            $reg = BlackDuckServiceRegistry::rowBySlug($prefilledServiceSlug);
            if ($reg !== null) {
                $prefillMessage = 'Запись на услугу: «'.(string) ($reg['title'] ?? $prefilledServiceSlug).'».'."\n\n";
            }
        }
        $showServiceField = $requires && $isBlackDuck && $options !== [];

        return [
            'requires_service_selector' => $requires,
            'service_options' => $options,
            'prefilled_service_slug' => $prefilledServiceSlug,
            'prefill_message' => $prefillMessage,
            'show_service_field' => $showServiceField,
        ];
    }
}
