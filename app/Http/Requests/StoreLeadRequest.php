<?php

namespace App\Http\Requests;

use App\ContactChannels\ContactChannelType;
use App\ContactChannels\TenantContactChannelsStore;
use App\Support\Phone\IntlPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
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

        $tenantId = $tenant?->id ?? 0;

        return [
            'motorcycle_id' => [
                'nullable',
                Rule::exists('motorcycles', 'id')->where('tenant_id', $tenantId),
            ],
            'rental_date_from' => ['nullable', 'date'],
            'rental_date_to' => ['nullable', 'date', 'after_or_equal:rental_date_from'],
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
            'email' => ['nullable', 'email', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:50'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'preferred_contact_channel' => ['required', 'string', Rule::in($allowed)],
            'preferred_contact_value' => ['nullable', 'string', 'max:500'],
            'agree_to_terms' => [
                Rule::excludeIf(fn () => ! $this->filled('motorcycle_id')),
                'required',
                'accepted',
            ],
            'agree_to_privacy' => [
                Rule::excludeIf(fn () => ! $this->filled('motorcycle_id')),
                'required',
                'accepted',
            ],
        ];
    }
}
