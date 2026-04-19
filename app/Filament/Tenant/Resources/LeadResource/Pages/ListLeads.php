<?php

namespace App\Filament\Tenant\Resources\LeadResource\Pages;

use App\Filament\Exports\LeadExporter;
use App\Filament\Tenant\Forms\ManualOperatorBookingForm;
use App\Filament\Tenant\Resources\LeadResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\Models\Booking;
use App\Models\Lead;
use App\Product\CRM\DTO\ManualLeadCreateData;
use App\Product\CRM\ManualLeadBookingService;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    public function getTitle(): string|Htmlable
    {
        return LeadResource::getPluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'leadsWhatIs',
                [
                    'Входящие обращения с сайта: потенциальные клиенты и запросы.',
                    'Новые заявки обрабатывайте в первую очередь.',
                    '',
                    'Статус и ответственный видны только вашей команде.',
                ],
                'Справка по лидам',
            ),
            Action::make('create_manual_lead')
                ->label('Добавить обращение')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => Gate::allows('create', Lead::class))
                ->modalSubmitActionLabel('Создать')
                ->form(ManualOperatorBookingForm::leadCreateComponents())
                ->action(function (array $data): void {
                    $tenant = currentTenant();
                    if ($tenant === null) {
                        Notification::make()
                            ->title('Контекст клиента недоступен')
                            ->body('Обновите страницу или войдите в кабинет снова.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $createBooking = (bool) ($data['create_booking'] ?? false)
                        && Gate::allows('create', Booking::class);
                    $createCrm = ManualOperatorBookingForm::effectiveCreateCrm($data);

                    $fromYmd = $createBooking
                        ? ManualOperatorBookingForm::toYmd($data['booking_rental_date_from'] ?? null)
                        : ManualOperatorBookingForm::toYmd($data['rental_date_from'] ?? null);
                    $toYmd = $createBooking
                        ? ManualOperatorBookingForm::toYmd($data['booking_rental_date_to'] ?? null)
                        : ManualOperatorBookingForm::toYmd($data['rental_date_to'] ?? null);

                    $motorcycleId = isset($data['motorcycle_id']) ? (int) $data['motorcycle_id'] : null;
                    $rentalUnitId = isset($data['rental_unit_id']) ? (int) $data['rental_unit_id'] : null;

                    try {
                        $result = app(ManualLeadBookingService::class)->createManualLead(new ManualLeadCreateData(
                            tenantId: $tenant->id,
                            name: (string) $data['name'],
                            phone: (string) $data['phone'],
                            email: $data['email'] ?? null,
                            comment: $data['comment'] ?? null,
                            motorcycleId: $motorcycleId ?: null,
                            rentalDateFromYmd: $fromYmd,
                            rentalDateToYmd: $toYmd,
                            createCrm: $createCrm,
                            createBooking: $createBooking,
                            rentalUnitId: $rentalUnitId ?: null,
                        ));
                    } catch (ValidationException $e) {
                        throw $e;
                    } catch (AuthorizationException $e) {
                        Notification::make()
                            ->title('Действие недоступно')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Не удалось создать обращение')
                            ->body('Попробуйте ещё раз. Если ошибка повторяется — сообщите в поддержку.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $bodyParts = ['Лид №'.$result->lead->id];
                    if ($result->crmRequest !== null) {
                        $bodyParts[] = 'CRM №'.$result->crmRequest->id;
                    }
                    if ($result->booking !== null) {
                        $bodyParts[] = 'Бронь №'.$result->booking->id;
                    }

                    Notification::make()
                        ->title('Обращение создано')
                        ->body(implode(' · ', $bodyParts))
                        ->success()
                        ->send();

                    $this->dispatch('$refresh');
                }),
            ExportAction::make()
                ->exporter(LeadExporter::class)
                ->visible(fn () => Gate::allows('export_leads')),
        ];
    }
}
