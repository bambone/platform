<?php

namespace App\Filament\Platform\Resources\PlatformUserResource\Pages;

use App\Auth\AccessRoles;
use App\Filament\Platform\Resources\PlatformUserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditPlatformUser extends EditRecord
{
    protected static string $resource = PlatformUserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['platform_roles'] = $this->record->roles
            ->whereIn('name', AccessRoles::platformRoles())
            ->pluck('name')
            ->values()
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['platform_roles']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->syncPlatformRoles($this->record, $this->data['platform_roles'] ?? []);
    }

    private function syncPlatformRoles(User $user, array $platformRoles): void
    {
        $selected = array_values(array_intersect($platformRoles, AccessRoles::platformRoles()));
        $keep = $user->roles->whereNotIn('name', AccessRoles::platformRoles())->pluck('name')->all();
        $user->syncRoles(array_merge($keep, $selected));
    }
}
