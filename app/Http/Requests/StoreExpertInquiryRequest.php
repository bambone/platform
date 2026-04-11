<?php

namespace App\Http\Requests;

use App\ContactChannels\ContactChannelType;
use App\ContactChannels\TenantContactChannelsStore;
use App\Support\Phone\IntlPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpertInquiryRequest extends FormRequest
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

        $this->merge([
            'preferred_contact_channel' => $this->input('preferred_contact_channel', ContactChannelType::Phone->value),
        ]);
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:16',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! IntlPhoneNormalizer::validatePhone($value)) {
                        $fail('Укажите корректный телефон в международном формате (например +7 для России).');
                    }
                },
            ],
            'goal_text' => ['required', 'string', 'max:2000'],
            'preferred_schedule' => ['nullable', 'string', 'max:500'],
            'district' => ['nullable', 'string', 'max:255'],
            'has_own_car' => ['nullable', 'string', 'max:32'],
            'transmission' => ['nullable', 'string', 'max:64'],
            'has_license' => ['nullable', 'string', 'max:32'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'program_slug' => ['nullable', 'string', 'max:128'],
            'expert_domain' => ['nullable', 'string', 'max:64'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'preferred_contact_channel' => ['required', 'string', Rule::in($allowed)],
            'preferred_contact_value' => ['nullable', 'string', 'max:500'],
        ];
    }
}
