<?php

namespace App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages;

use App\Filament\Tenant\Resources\TenantServiceProgramResource;
use App\Filament\Tenant\Resources\TenantServiceProgramResource\Concerns\NormalizesProgramListJsonForForm;
use App\Filament\Tenant\Resources\TenantServiceProgramResource\Concerns\RefreshesBlackDuckTenantPublicContent;
use App\Livewire\Concerns\InteractsWithTenantPublicFilePicker;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Livewire\WithFileUploads;

class EditTenantServiceProgram extends EditRecord
{
    use InteractsWithTenantPublicFilePicker;
    use NormalizesProgramListJsonForForm;
    use RefreshesBlackDuckTenantPublicContent;
    use WithFileUploads;

    protected static string $resource = TenantServiceProgramResource::class;

    public function getFooter(): ?View
    {
        return view('filament.tenant.resources.tenant-service-program-resource.partials.public-file-picker-footer');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->normalizeProgramJsonListsForForm($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeProgramJsonListsForSave($data);
    }

    protected function afterSave(): void
    {
        parent::afterSave();
        $this->afterBlackDuckServiceProgramMutation($this->getRecord());
    }
}
