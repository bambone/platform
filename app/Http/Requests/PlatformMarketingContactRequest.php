<?php

namespace App\Http\Requests;

use App\ContactChannels\PlatformMarketingContactChannelsStore;
use App\ContactChannels\PlatformMarketingVisitorContactPayloadBuilder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformMarketingContactRequest extends FormRequest
{
    /**
     * @var array{phone: ?string, preferred_contact_channel: string, preferred_contact_value: ?string, visitor_contact_channels_json: list<array<string, mixed>>}|null
     */
    private ?array $resolvedContactPayload = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{phone: ?string, preferred_contact_channel: string, preferred_contact_value: ?string, visitor_contact_channels_json: list<array<string, mixed>>}
     */
    public function resolvedContactPayload(): array
    {
        if ($this->resolvedContactPayload === null) {
            throw new \LogicException('Contact payload is available only after successful validation.');
        }

        return $this->resolvedContactPayload;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $intentKeys = array_keys(config('platform_marketing.contact_page.intents', []));
        $allowedPreferred = app(PlatformMarketingContactChannelsStore::class)->allowedPreferredChannelIds();

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'preferred_contact_channel' => ['required', 'string', Rule::in($allowedPreferred)],
            'preferred_contact_value' => ['nullable', 'string', 'max:500'],
            'message' => ['required', 'string', 'min:15', 'max:2000'],
            'intent' => ['nullable', 'string', Rule::in($intentKeys)],
            'company_site' => ['prohibited'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите, как к вам обращаться.',
            'email.required' => 'Нужен email — на него пришлём ответ.',
            'email.email' => 'Проверьте формат email.',
            'preferred_contact_channel.required' => 'Выберите предпочитаемый способ связи.',
            'preferred_contact_channel.in' => 'Выбран недопустимый способ связи.',
            'phone.max' => 'Телефон слишком длинный.',
            'preferred_contact_value.max' => 'Слишком длинное значение для контакта в канале.',
            'message.required' => 'Опишите нишу и задачу — без этого сложнее подготовить ответ.',
            'message.min' => 'Коротко опишите задачу (не менее :min символов) — так мы лучше подготовим ответ.',
            'intent.in' => 'Выберите тему обращения из списка.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            try {
                $this->resolvedContactPayload = app(PlatformMarketingVisitorContactPayloadBuilder::class)->build([
                    'email' => (string) $this->input('email', ''),
                    'phone' => $this->input('phone'),
                    'preferred_contact_channel' => (string) $this->input('preferred_contact_channel', ''),
                    'preferred_contact_value' => $this->input('preferred_contact_value'),
                ]);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $attr => $messages) {
                    foreach ($messages as $message) {
                        $v->errors()->add($attr, $message);
                    }
                }
            }
        });
    }
}
