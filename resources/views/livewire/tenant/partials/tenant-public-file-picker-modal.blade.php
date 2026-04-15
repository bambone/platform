@php
    /** @var string $uploadSlotAttribute */
    $uploadSlotAttribute = $uploadSlotAttribute ?? 'data-tenant-public-upload-input';
@endphp
@include('livewire.tenant.partials.tenant-public-file-picker-inputs', ['uploadSlotAttribute' => $uploadSlotAttribute])
@include('livewire.tenant.partials.tenant-public-file-picker-overlay', ['mount' => 'teleport'])
