<?php

namespace App\Filament\Tenant\Resources\CrmRequestResource\Pages;

use App\Filament\Tenant\Resources\CrmRequestResource;
use App\Models\CrmRequest;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ViewCrmRequest extends ViewRecord
{
    protected static string $resource = CrmRequestResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();
        if ($record instanceof CrmRequest) {
            $name = trim((string) $record->name);

            return $name !== '' ? $name : 'Обращение №'.$record->getKey();
        }

        return parent::getTitle();
    }

    /** Имя и сводка внутри Livewire — дублирующий heading страницы скрываем. */
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var CrmRequest $record */
        $record = static::getResource()::getEloquentQuery()
            ->whereKey($key)
            ->firstOrFail();

        return $record;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.shared.crm.crm-workspace-modal')
                    ->columnSpanFull()
                    ->viewData(fn (): array => [
                        'crmRequestId' => (int) $this->getRecord()->getKey(),
                    ]),
            ]);
    }

    public function getBreadcrumb(): string
    {
        return 'Просмотр';
    }
}
