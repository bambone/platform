<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ContactChannels\ContactChannelType;
use App\ContactChannels\TenantContactChannelsStore;
use App\Models\PageSection;
use App\Models\TenantServiceProgram;
use App\Services\PublicSite\ContactInquiryFormPresenter;
use App\Support\Phone\IntlPhoneNormalizer;
use App\Tenant\BlackDuck\BlackDuckServiceProgramCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContactInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => IntlPhoneNormalizer::normalizePhone((string) $this->input('phone')),
            ]);
        }

        if (! $this->has('preferred_contact_channel')) {
            $this->merge([
                'preferred_contact_channel' => ContactChannelType::Phone->value,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = currentTenant();
        $allowed = $tenant !== null
            ? app(TenantContactChannelsStore::class)->allowedPreferredChannelIds($tenant->id)
            : [ContactChannelType::Phone->value];

        $tenantId = $tenant?->id ?? 0;

        return [
            'page_section_id' => [
                'required',
                'integer',
                Rule::exists('page_sections', 'id')->where('tenant_id', $tenantId),
            ],
            'inquiry_service_slug' => ['nullable', 'string', 'max:'.TenantServiceProgram::SLUG_MAX_LENGTH],
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:28',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! IntlPhoneNormalizer::validatePhone($value)) {
                        $fail('Укажите корректный телефон в международном формате (например +7 для России).');
                    }
                },
            ],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:3', 'max:5000'],
            'preferred_contact_channel' => ['required', 'string', Rule::in($allowed)],
            'preferred_contact_value' => ['nullable', 'string', 'max:500'],
            'consent_accepted' => ['sometimes', 'boolean'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $tenant = currentTenant();
            if ($tenant === null) {
                $v->errors()->add('tenant', 'Контекст сайта не определён.');

                return;
            }

            $sectionId = (int) $this->input('page_section_id');
            $section = PageSection::query()
                ->whereKey($sectionId)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($section === null) {
                $v->errors()->add('page_section_id', 'Блок формы не найден.');

                return;
            }

            if ($section->section_type !== 'contact_inquiry') {
                $v->errors()->add('page_section_id', 'Некорректный блок формы.');

                return;
            }

            $data = is_array($section->data_json) ? $section->data_json : [];
            if (! ($data['enabled'] ?? true)) {
                $v->errors()->add('page_section_id', 'Форма отключена.');

                return;
            }

            if (($data['show_email'] ?? true) === false && filled($this->input('email'))) {
                $v->errors()->add('email', 'Поле email отключено для этой формы.');
            }

            if (($data['consent_enabled'] ?? false) === true && ! $this->boolean('consent_accepted')) {
                $v->errors()->add('consent_accepted', 'Нужно согласие на обработку данных.');
            }

            if (filled($this->input('inquiry_service_slug'))) {
                $raw = (string) $this->input('inquiry_service_slug');
                $n = TenantServiceProgram::normalizePublicSlugForStorage($raw);
                if ($n === '' || ! TenantServiceProgram::isPublicInquirySlugFormat($n)) {
                    $v->errors()->add('inquiry_service_slug', 'Некорректный идентификатор услуги.');
                }
            }

            $requiresService = ContactInquiryFormPresenter::sectionRequiresServiceSelector($data, $tenant);
            if ($requiresService && $tenant->theme_key === 'black_duck') {
                $slugs = array_column(BlackDuckServiceProgramCatalog::inquiryFormServiceOptions((int) $tenant->id), 'slug');
                if ($slugs === []) {
                    if (filled($this->input('inquiry_service_slug'))) {
                        $v->errors()->add(
                            'inquiry_service_slug',
                            'Сейчас в форме нет доступных направлений — не указывайте услугу вручную.',
                        );
                    }

                    return;
                }
                $slug = $this->input('inquiry_service_slug');
                if (! is_string($slug) || $slug === '' || ! in_array($slug, $slugs, true)) {
                    $v->errors()->add('inquiry_service_slug', 'Выберите направление из списка.');
                }
            }
        });
    }
}
