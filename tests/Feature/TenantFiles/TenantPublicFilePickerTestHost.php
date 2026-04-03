<?php

namespace Tests\Feature\TenantFiles;

use App\Livewire\Concerns\InteractsWithTenantPublicFilePicker;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Минимальный хост для проверки {@see InteractsWithTenantPublicFilePicker} без Filament.
 */
final class TenantPublicFilePickerTestHost extends Component
{
    use InteractsWithTenantPublicFilePicker;
    use WithFileUploads;

    /** @var array<string, mixed> */
    public array $sectionFormData = [
        'data_json' => [
            'background_image' => '',
        ],
    ];

    public function render()
    {
        return view()->file(base_path('tests/Support/views/tenant-public-file-picker-test-host.blade.php'));
    }
}
