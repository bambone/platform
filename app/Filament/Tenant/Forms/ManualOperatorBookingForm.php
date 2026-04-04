<?php

namespace App\Filament\Tenant\Forms;

use App\Models\Booking;
use App\Models\CrmRequest;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Product\CRM\DTO\ManualBookingCreateData;
use App\Product\CRM\ManualLeadBookingService;
use App\Rules\ValidIntlPhone;
use App\Support\RussianPhone;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Поля модалок «ручное обращение / бронирование» (tenant admin). См. {@see ManualLeadBookingService}.
 */
final class ManualOperatorBookingForm
{
    private static function operatorPhoneTextInput(): TextInput
    {
        return TextInput::make('phone')
            ->label('Телефон')
            ->tel()
            ->telRegex(RussianPhone::filamentTelDisplayRegex())
            ->required()
            ->mask('+7 (999) 999-99-99')
            ->placeholder('+7 (___) ___-__-__')
            ->rule(new ValidIntlPhone)
            ->maxLength(40)
            ->helperText('Как в форме на сайте: проверка международного формата; маска удобна для РФ, для других стран вставьте номер с +.');
    }

    private static function operatorEmailTextInput(): TextInput
    {
        return TextInput::make('email')
            ->label('Email')
            ->email()
            ->nullable()
            ->maxLength(255);
    }

    /**
     * @return array<int, Component>
     */
    public static function leadCreateComponents(): array
    {
        return [
            TextInput::make('name')
                ->label('Имя / как обращаться')
                ->required()
                ->maxLength(255),
            self::operatorPhoneTextInput(),
            self::operatorEmailTextInput(),
            TextInput::make('messenger')
                ->label('Мессенджер')
                ->maxLength(255)
                ->helperText('Ник или канал: WhatsApp, Telegram и т.д.'),
            Textarea::make('comment')
                ->label('Комментарий')
                ->rows(3)
                ->columnSpanFull(),
            Select::make('motorcycle_id')
                ->label('Интерес к технике')
                ->placeholder('Не выбрано')
                ->options(fn (): array => self::motorcycleOptions())
                ->searchable()
                ->preload()
                ->live()
                ->required(fn (Get $get): bool => (bool) $get('create_booking')),
            DatePicker::make('rental_date_from')
                ->label('Дата начала (интерес)')
                ->native(false)
                ->live(),
            DatePicker::make('rental_date_to')
                ->label('Дата окончания (интерес)')
                ->native(false)
                ->live(),
            Checkbox::make('create_crm')
                ->label('Создать карточку CRM')
                ->default(true)
                ->helperText('Можно отключить для короткой фиксации без тикета.')
                ->visible(fn (): bool => Gate::allows('create', CrmRequest::class))
                ->live(),
            Checkbox::make('create_booking')
                ->label('Сразу создать бронирование')
                ->default(false)
                ->helperText('Нужны техника, единица парка и даты брони.')
                ->visible(fn (): bool => Gate::allows('create', Booking::class))
                ->live(),
            Select::make('rental_unit_id')
                ->label('Единица парка')
                ->placeholder('Выберите единицу')
                ->options(fn (Get $get): array => self::rentalUnitOptionsFiltered($get('motorcycle_id')))
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => (bool) $get('create_booking'))
                ->required(fn (Get $get): bool => (bool) $get('create_booking')),
            DatePicker::make('booking_rental_date_from')
                ->label('Начало брони')
                ->native(false)
                ->visible(fn (Get $get): bool => (bool) $get('create_booking'))
                ->required(fn (Get $get): bool => (bool) $get('create_booking')),
            DatePicker::make('booking_rental_date_to')
                ->label('Окончание брони')
                ->native(false)
                ->visible(fn (Get $get): bool => (bool) $get('create_booking'))
                ->required(fn (Get $get): bool => (bool) $get('create_booking')),
        ];
    }

    /**
     * Бронирование с опциями «новое обращение» / CRM (для календаря и списка броней).
     *
     * @return array<int, Component>
     */
    public static function standaloneBookingComponents(): array
    {
        return [
            Checkbox::make('create_crm')
                ->label('Создать карточку CRM')
                ->default(true)
                ->helperText('Снимите, если нужна только операционная бронь без тикета CRM.')
                ->visible(fn (): bool => Gate::allows('create', CrmRequest::class))
                ->live(),
            TextInput::make('name')
                ->label('Имя клиента')
                ->required()
                ->maxLength(255),
            self::operatorPhoneTextInput(),
            self::operatorEmailTextInput(),
            TextInput::make('messenger')
                ->label('Мессенджер')
                ->maxLength(255),
            Textarea::make('comment')
                ->label('Комментарий')
                ->rows(2)
                ->columnSpanFull(),
            Select::make('motorcycle_id')
                ->label('Техника')
                ->options(fn (): array => self::motorcycleOptions())
                ->searchable()
                ->preload()
                ->required()
                ->live(),
            Select::make('rental_unit_id')
                ->label('Единица парка')
                ->options(fn (Get $get): array => self::rentalUnitOptionsFiltered($get('motorcycle_id')))
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('start_date')
                ->label('Начало')
                ->native(false)
                ->required(),
            DatePicker::make('end_date')
                ->label('Окончание')
                ->native(false)
                ->required(),
        ];
    }

    /**
     * Бронирование от существующего лида (без нового CRM).
     *
     * @return array<int, Component>
     */
    public static function bookingFromLeadComponents(): array
    {
        return [
            Select::make('motorcycle_id')
                ->label('Техника')
                ->options(fn (): array => self::motorcycleOptions())
                ->searchable()
                ->preload()
                ->required()
                ->live(),
            Select::make('rental_unit_id')
                ->label('Единица парка')
                ->options(fn (Get $get): array => self::rentalUnitOptionsFiltered($get('motorcycle_id')))
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('start_date')
                ->label('Начало')
                ->native(false)
                ->required(),
            DatePicker::make('end_date')
                ->label('Окончание')
                ->native(false)
                ->required(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private static function motorcycleOptions(): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        return Motorcycle::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->limit(500)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function rentalUnitOptionsFiltered(mixed $motorcycleId): array
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return [];
        }

        $q = RentalUnit::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->with('motorcycle:id,name');

        if (filled($motorcycleId)) {
            $q->where('motorcycle_id', (int) $motorcycleId);
        }

        return $q->orderBy('id')
            ->get()
            ->mapWithKeys(function (RentalUnit $u): array {
                $label = $u->motorcycle
                    ? sprintf('#%d — %s', $u->id, $u->motorcycle->name)
                    : sprintf('Единица #%d', $u->id);

                return [$u->id => $label];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function toYmd(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function effectiveCreateCrm(array $data): bool
    {
        if (! Gate::allows('create', CrmRequest::class)) {
            return false;
        }

        return (bool) ($data['create_crm'] ?? true);
    }

    /**
     * Единое header-действие «Добавить бронирование» для календаря и списка броней (фаза 3 плана: без дублирования схемы).
     *
     * @param  (Closure(): array<string, mixed>)|null  $fillForm
     * @param  (Closure(): void)|null  $afterSubmit
     */
    public static function standaloneBookingCreateAction(?Closure $fillForm = null, ?Closure $afterSubmit = null): Action
    {
        $action = Action::make('create_manual_booking')
            ->label('Добавить бронирование')
            ->icon('heroicon-o-plus-circle')
            ->visible(fn (): bool => Gate::allows('create', Booking::class))
            ->form(self::standaloneBookingComponents())
            ->action(function (array $data) use ($afterSubmit): void {
                self::submitStandaloneManualBooking($data);
                if ($afterSubmit !== null) {
                    ($afterSubmit)();
                }
            });

        if ($fillForm !== null) {
            $action->fillForm($fillForm);
        }

        return $action;
    }

    /**
     * Общий submit для модалки «Добавить бронирование» (календарь / список).
     *
     * @param  array<string, mixed>  $data
     */
    public static function submitStandaloneManualBooking(array $data): void
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return;
        }

        $start = self::toYmd($data['start_date'] ?? null);
        $end = self::toYmd($data['end_date'] ?? null);
        if ($start === null || $end === null) {
            throw ValidationException::withMessages([
                'start_date' => 'Укажите даты бронирования.',
            ]);
        }

        $createCrm = self::effectiveCreateCrm($data);

        app(ManualLeadBookingService::class)->createManualBooking(new ManualBookingCreateData(
            tenantId: $tenant->id,
            name: (string) $data['name'],
            motorcycleId: (int) $data['motorcycle_id'],
            rentalUnitId: (int) $data['rental_unit_id'],
            startDateYmd: $start,
            endDateYmd: $end,
            phone: (string) $data['phone'],
            email: $data['email'] ?? null,
            comment: $data['comment'] ?? null,
            messenger: $data['messenger'] ?? null,
            createLead: true,
            createCrm: $createCrm,
        ));

        Notification::make()
            ->title('Бронирование создано')
            ->success()
            ->send();
    }
}
