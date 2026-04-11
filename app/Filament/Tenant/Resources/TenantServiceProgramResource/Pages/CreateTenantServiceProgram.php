<?php

namespace App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages;

use App\Filament\Tenant\Resources\TenantServiceProgramResource;
use App\Filament\Tenant\Resources\TenantServiceProgramResource\Concerns\NormalizesProgramListJsonForForm;
use App\Livewire\Concerns\InteractsWithTenantPublicFilePicker;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\View\View;
use Livewire\WithFileUploads;

class CreateTenantServiceProgram extends CreateRecord
{
    use InteractsWithTenantPublicFilePicker;
    use NormalizesProgramListJsonForForm;
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
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeProgramJsonListsForSave($data);
    }
}
