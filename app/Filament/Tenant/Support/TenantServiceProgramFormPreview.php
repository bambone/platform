<?php

namespace App\Filament\Tenant\Support;

use App\MediaPresentation\PresentationData;
use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Money\MoneyBindingRegistry;
use App\Money\MoneyParser;
use App\Tenant\Expert\ServiceProgramType;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Transient {@see TenantServiceProgram} from current Filament form state for WYSIWYG preview
 * (same fields as on save, so overlap / featured / price / lists match the landing card).
 */
final class TenantServiceProgramFormPreview
{
    /**
     * @param  Get  $get  Form accessor (same state as save, before dehydrate of money from display string is optional — we parse display).
     */
    public static function makeFromGet(Get $get, Tenant $tenant, ?int $recordId = null): TenantServiceProgram
    {
        return self::fromGetter(static fn (string $key) => $get($key), $tenant, $recordId);
    }

    /**
     * @param  callable(string): mixed  $get  Тот же набор ключей, что и у Filament-{@see Get} (тесты, фабрики превью).
     */
    public static function makeFromGetCallable(callable $get, Tenant $tenant, ?int $recordId = null): TenantServiceProgram
    {
        return self::fromGetter($get, $tenant, $recordId);
    }

    /**
     * @param  array<string, mixed>  $state  Flat form state (same keys as {@see makeFromGet} reads).
     */
    public static function makeFromFormStateArray(array $state, Tenant $tenant, ?int $recordId = null): TenantServiceProgram
    {
        return self::fromGetter(static function (string $key) use ($state) {
            return array_key_exists($key, $state) ? $state[$key] : null;
        }, $tenant, $recordId);
    }

    /**
     * @param  callable(string): mixed  $get
     */
    private static function fromGetter(callable $get, Tenant $tenant, ?int $recordId = null): TenantServiceProgram
    {
        $cover = $get('cover_presentation');
        if (! is_array($cover)) {
            $cover = [];
        }
        $presentation = PresentationData::fromArray($cover);

        $audience = self::repeaterLinesToJsonList($get('audience_json'));
        $outcomes = self::repeaterLinesToJsonList($get('outcomes_json'));

        $priceAmount = self::parsePriceAmountForPreview($get('price_amount'), $tenant);

        $typeRaw = (string) ($get('program_type') ?? '');
        if ($typeRaw === '' || ! TenantServiceProgram::isAllowedProgramType($typeRaw)) {
            $typeRaw = ServiceProgramType::Program->value;
        }

        $data = [
            'tenant_id' => (int) $tenant->id,
            'slug' => trim((string) ($get('slug') ?? '')) !== '' ? trim((string) $get('slug')) : 'preview',
            'title' => (string) ($get('title') ?? ''),
            'teaser' => (string) ($get('teaser') ?? ''),
            'description' => (string) ($get('description') ?? ''),
            'audience_json' => $audience,
            'outcomes_json' => $outcomes,
            'cover_image_ref' => trim((string) ($get('cover_image_ref') ?? '')),
            'cover_mobile_ref' => trim((string) ($get('cover_mobile_ref') ?? '')),
            'cover_image_alt' => (string) ($get('cover_image_alt') ?? ''),
            'cover_object_position' => null,
            'cover_presentation_json' => $presentation,
            'duration_label' => (string) ($get('duration_label') ?? ''),
            'format_label' => (string) ($get('format_label') ?? ''),
            'price_amount' => $priceAmount,
            'price_prefix' => (string) ($get('price_prefix') ?? ''),
            'program_type' => $typeRaw,
            'is_featured' => (bool) ($get('is_featured') ?? false),
            'is_visible' => (bool) ($get('is_visible') ?? true),
            'sort_order' => (int) ($get('sort_order') ?? 0),
        ];
        if ($recordId !== null) {
            $data['id'] = $recordId;
        }

        $model = (new TenantServiceProgram)->newInstance($data, $recordId !== null);
        if ($recordId !== null) {
            $model->exists = true;
        }
        $model->setRelation('tenant', $tenant);

        return $model;
    }

    /**
     * @return list<string>
     */
    private static function repeaterLinesToJsonList(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }
        $lines = [];
        foreach ($state as $row) {
            if (! is_array($row)) {
                continue;
            }
            $t = trim((string) ($row['text'] ?? ''));
            if ($t !== '') {
                $lines[] = $t;
            }
        }

        return $lines;
    }

    private static function parsePriceAmountForPreview(mixed $raw, Tenant $tenant): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw)) {
            return $raw;
        }
        if (is_numeric($raw) && (string) (int) $raw === (string) $raw) {
            return (int) $raw;
        }
        if (! is_string($raw)) {
            return null;
        }
        if (trim($raw) === '') {
            return null;
        }
        try {
            return app(MoneyParser::class)->parseToStorageInt(
                $raw,
                MoneyBindingRegistry::TENANT_SERVICE_PROGRAM_PRICE_AMOUNT,
                $tenant,
                allowEmptyAsZero: false,
            );
        } catch (ValidationException|Throwable) {
            return null;
        }
    }
}
