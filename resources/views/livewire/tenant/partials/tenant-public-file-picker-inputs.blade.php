@php
    /** @var string $uploadSlotAttribute имя data-* атрибута для querySelector */
    $uploadSlotAttribute = $uploadSlotAttribute ?? 'data-tenant-public-upload-input';
@endphp
{{-- Скрытые input: изображения (TenantPublicImagePicker) и видео MP4/WebM (TenantPublicMediaPicker). --}}
<input
    type="file"
    accept="image/*"
    {{ $uploadSlotAttribute }}
    wire:model="tenantPublicImageUploadBuffer"
    class="sr-only"
/>
<input
    type="file"
    accept="video/mp4,video/webm,.mp4,.webm"
    data-tenant-public-video-upload-input
    wire:model="tenantPublicVideoUploadBuffer"
    class="sr-only"
/>
