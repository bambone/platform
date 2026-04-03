<x-filament-panels::page>
    @include('livewire.tenant.partials.tenant-public-file-picker-modal', ['uploadSlotAttribute' => 'data-settings-tenant-upload-input'])

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Сохранить
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
