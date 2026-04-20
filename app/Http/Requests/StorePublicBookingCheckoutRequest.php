<?php

namespace App\Http\Requests;

use App\ContactChannels\ContactChannelRegistry;
use App\ContactChannels\ContactChannelType;
use App\ContactChannels\PreferredContactValueMessages;
use App\ContactChannels\TenantContactChannelsStore;
use App\ContactChannels\VisitorContactNormalizer;
use App\Support\Phone\IntlPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicBookingCheckoutRequest extends FormRequest
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
            'agree_to_terms' => ['required', 'accepted'],
            'agree_to_privacy' => ['required', 'accepted'],
            'customer_name' => ['required', 'string', 'max:255'],
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
            'customer_comment' => ['nullable', 'string', 'max:1000'],
            'preferred_contact_channel' => ['required', 'string', Rule::in($allowed)],
            'preferred_contact_value' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $ch = (string) $this->input('preferred_contact_channel', '');
            if (! ContactChannelRegistry::requiresVisitorValue($ch)) {
                return;
            }

            $raw = trim((string) $this->input('preferred_contact_value', ''));
            if ($raw === '') {
                $v->errors()->add('preferred_contact_value', PreferredContactValueMessages::requiredRu($ch));

                return;
            }

            if ($ch === ContactChannelType::Telegram->value) {
                if (VisitorContactNormalizer::normalizeTelegram($raw) === null) {
                    $v->errors()->add('preferred_contact_value', PreferredContactValueMessages::invalidFormatRu($ch));
                }

                return;
            }

            if ($ch === ContactChannelType::Vk->value) {
                if (VisitorContactNormalizer::normalizeVk($raw) === null) {
                    $v->errors()->add('preferred_contact_value', PreferredContactValueMessages::invalidFormatRu($ch));
                }

                return;
            }

            if ($ch === ContactChannelType::Max->value) {
                if (VisitorContactNormalizer::normalizeMax($raw) === null) {
                    $v->errors()->add('preferred_contact_value', PreferredContactValueMessages::invalidFormatRu($ch));
                }
            }
        });
    }
}
