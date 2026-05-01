<?php

namespace App\Http\Requests;

use App\ContactChannels\ContactChannelType;
use App\ContactChannels\TenantContactChannelsStore;
use App\Support\Phone\IntlPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreExpertInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $honeypot = trim((string) $this->input('website', ''));
        if ($honeypot !== '') {
            $t = currentTenant();
            Log::warning('expert_inquiry_honeypot_triggered', [
                'tenant_id' => $t?->id,
                'ip' => $this->ip(),
                'ua' => $this->userAgent(),
            ]);
            $enUi = $t !== null && $t->themeKey() === 'expert_pr';
            throw new HttpResponseException(response()->json([
                'success' => true,
                'message' => $enUi
                    ? 'Thank you. If this matches your inquiry, we will follow up shortly.'
                    : 'Спасибо! Заявка отправлена. Мы свяжемся с вами.',
            ]));
        }

        if ($this->has('phone')) {
            $this->merge([
                'phone' => IntlPhoneNormalizer::normalizePhone((string) $this->input('phone')),
            ]);
        }

        $this->merge([
            'preferred_contact_channel' => $this->input('preferred_contact_channel', ContactChannelType::Phone->value),
        ]);

        if ($this->has('preferred_schedule')) {
            $this->merge([
                'preferred_schedule' => trim((string) $this->input('preferred_schedule')),
            ]);
        }

        $tenantForPr = currentTenant();
        if ($this->has('contact_email')) {
            $this->merge([
                'contact_email' => strtolower(trim((string) $this->input('contact_email'))),
            ]);
        }

        if ($this->has('briefing_website')) {
            $w = trim((string) $this->input('briefing_website'));
            if ($w !== '' && ! preg_match('#^https?://#i', $w)) {
                $w = 'https://'.$w;
            }
            $this->merge(['briefing_website' => $w]);
        }

        if ($tenantForPr !== null && $tenantForPr->themeKey() === 'expert_pr') {
            $phNorm = IntlPhoneNormalizer::normalizePhone((string) ($this->input('phone') ?? ''));
            $phoneOk = $phNorm !== '' && IntlPhoneNormalizer::validatePhone($phNorm);
            $em = strtolower(trim((string) ($this->input('contact_email') ?? '')));
            $emailOk = $em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) !== false;
            if (! $phoneOk && $emailOk) {
                $this->merge([
                    'preferred_contact_channel' => ContactChannelType::Email->value,
                    'preferred_contact_value' => $em,
                    'phone' => '',
                ]);
            }
            $ch = (string) $this->input('preferred_contact_channel', ContactChannelType::Phone->value);
            if ($ch === ContactChannelType::Email->value && $emailOk) {
                $this->merge(['preferred_contact_value' => $em]);
            }
        }

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
            if (! $this->has($k)) {
                continue;
            }
            $v = $this->input($k);
            $this->merge([
                $k => is_string($v) ? trim($v) : $v,
            ]);
        }

        if ($this->has('source_page')) {
            $this->merge([
                'source_page' => trim((string) $this->input('source_page')),
            ]);
        }

        if ($this->has('source_context')) {
            $this->merge([
                'source_context' => trim((string) $this->input('source_context')),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = currentTenant();
        $isExpertPr = $tenant !== null && $tenant->themeKey() === 'expert_pr';

        $allowed = $tenant !== null
            ? app(TenantContactChannelsStore::class)->allowedPreferredChannelIds($tenant->id)
            : [ContactChannelType::Phone->value];

        if ($isExpertPr) {
            $em = strtolower(trim((string) ($this->input('contact_email') ?? '')));
            $emailOk = $em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) !== false;
            if ($emailOk) {
                $allowed[] = ContactChannelType::Email->value;
            }
        }

        $allowed = array_values(array_unique($allowed));

        $phoneRules = $isExpertPr
            ? [
                'nullable',
                'string',
                'max:28',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! is_string($value) || ! IntlPhoneNormalizer::validatePhone($value)) {
                        $fail('Enter a valid international phone number (E.164), or use email instead.');
                    }
                },
            ]
            : [
                'required',
                'string',
                'max:16',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! IntlPhoneNormalizer::validatePhone($value)) {
                        $fail('Укажите корректный телефон в международном формате (например +7 для России).');
                    }
                },
            ];
        $programIdRule = ['nullable', 'integer'];
        if ($tenant !== null) {
            $programIdRule[] = Rule::exists('tenant_service_programs', 'id')->where(
                static fn ($q) => $q->where('tenant_id', $tenant->id),
            );
        }

        $st = (string) ($this->input('source_type') ?? '');
        $needsPrivacy = in_array($st, ['program_enrollment', 'enrollment_cta'], true);
        if ($tenant !== null && $tenant->themeKey() === 'black_duck') {
            $needsPrivacy = true;
        }
        if ($tenant !== null && $tenant->themeKey() === 'expert_pr') {
            $needsPrivacy = true;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => $phoneRules,
            'contact_email' => $isExpertPr
                ? ['nullable', 'email', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'goal_text' => ['required', 'string', 'max:2000'],
            'preferred_schedule' => [
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $this->assertValidPreferredScheduleInterval($value, $fail);
                },
            ],
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
            'source_type' => ['nullable', 'string', Rule::in(['program_enrollment', 'enrollment_cta'])],
            'source_page' => ['nullable', 'string', 'max:500'],
            'source_context' => ['nullable', 'string', 'max:128'],
            'program_id' => $programIdRule,
            'privacy_accepted' => $needsPrivacy ? ['required', 'accepted'] : ['nullable'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            /** @deprecated С клиента не влияет на тип CRM для black_duck — только аудит, см. payload client_crm_type_hint. */
            'crm_request_type' => [
                'nullable',
                'string',
                Rule::in([
                    'booking_request',
                    'quote_request',
                    'callback_request',
                    'messenger_request',
                    'question_request',
                    'expert_service_inquiry',
                ]),
            ],
            'company' => ['nullable', 'string', 'max:255'],
            'briefing_website' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $s = is_string($value) ? trim($value) : '';
                    if ($s === '') {
                        return;
                    }
                    if (filter_var($s, FILTER_VALIDATE_URL) === false) {
                        $fail('Enter a full website URL starting with https:// or leave empty.');
                    }
                },
            ],
            'industry' => ['nullable', 'string', 'max:255'],
            'budget_band' => ['nullable', 'string', 'max:120'],
            'timeline_horizon' => ['nullable', 'string', 'max:160'],
            'inquiry_intent' => [
                'nullable',
                'string',
                Rule::in([
                    'book_slot',
                    'price_quote',
                    'callback',
                    'messenger',
                    'question',
                    'service_inquiry',
                ]),
            ],
            'service_slug' => ['nullable', 'string', 'max:128'],
            'service_group' => ['nullable', 'string', 'max:64'],
            'vehicle_class' => ['nullable', 'string', 'max:64'],
            'vehicle_make' => ['nullable', 'string', 'max:64'],
            'vehicle_model' => ['nullable', 'string', 'max:64'],
            'needs_confirmation' => ['nullable', 'boolean'],
            'customer_goal' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $v): void {
            $tenant = currentTenant();
            if ($tenant === null) {
                return;
            }

            if ($tenant->themeKey() === 'expert_pr') {
                $ph = IntlPhoneNormalizer::normalizePhone((string) ($this->input('phone') ?? ''));
                $em = strtolower(trim((string) ($this->input('contact_email') ?? '')));
                $phoneOk = $ph !== '' && IntlPhoneNormalizer::validatePhone($ph);
                $emailOk = $em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL);
                if (! $phoneOk && ! $emailOk) {
                    $v->errors()->add(
                        'phone',
                        'Enter a valid phone number or a work email so we can reply.',
                    );
                }
                $chosen = (string) $this->input('preferred_contact_channel');
                if ($chosen === ContactChannelType::Email->value && ! $emailOk) {
                    $v->errors()->add(
                        'contact_email',
                        'Enter a valid work email so we can reply.',
                    );
                }
            }

            $ps = trim((string) $this->input('program_slug', ''));
            if ($ps !== '') {
                // Query table directly: Eloquent global scope follows currentTenant() and must not
                // desync from $tenant resolved for this host (tests + edge middleware order).
                $ok = DB::table('tenant_service_programs')
                    ->where('tenant_id', (int) $tenant->id)
                    ->where('slug', $ps)
                    ->where('is_visible', true)
                    ->exists();
                if (! $ok) {
                    $v->errors()->add('program_slug', 'Selected program is not available.');
                }
            }

            if ((string) $this->input('source_type') === 'program_enrollment') {
                $pidRaw = $this->input('program_id');
                $hasProgramId = is_numeric($pidRaw) && (int) $pidRaw > 0;
                $slugTrimmed = trim((string) $this->input('program_slug', ''));
                if (! $hasProgramId && $slugTrimmed === '') {
                    $v->errors()->add('program_slug', 'Choose a program.');
                }
            }
        });
    }

    /**
     * @param  \Closure(string): void  $fail
     */
    protected function assertValidPreferredScheduleInterval(mixed $value, \Closure $fail): void
    {
        if ($value === null) {
            return;
        }
        if (! is_string($value)) {
            $fail('Некорректное значение удобного времени.');

            return;
        }
        $trim = trim($value);
        if ($trim === '') {
            return;
        }
        if (str_contains($trim, "\n") || str_contains($trim, "\r")) {
            $fail('Укажите удобное время одной строкой или оставьте поле пустым.');

            return;
        }
        if (mb_strlen($trim) > 120) {
            $fail('Удобное время для связи — не длиннее 120 символов.');

            return;
        }
        if (! preg_match('/^(\d{2}:\d{2})\s*[\x{2013}\x{2014}-]\s*(\d{2}:\d{2})$/u', $trim, $m)) {
            // Свободная формулировка (например «будни после 18:00», «вечером»).
            return;
        }
        foreach ([$m[1], $m[2]] as $hm) {
            if (! self::isValidHourMinuteToken($hm)) {
                $fail('Время должно быть от 00:00 до 23:59.');

                return;
            }
        }
    }

    protected static function isValidHourMinuteToken(string $hm): bool
    {
        if (! preg_match('/^(\d{2}):(\d{2})$/', $hm, $p)) {
            return false;
        }
        $h = (int) $p[1];
        $i = (int) $p[2];

        return $h >= 0 && $h <= 23 && $i >= 0 && $i <= 59;
    }
}
