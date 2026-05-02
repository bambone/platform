<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ReviewResource\Pages;

use App\Filament\Tenant\Resources\ReviewImportSourceResource;
use App\Filament\Tenant\Resources\ReviewResource;
use App\Filament\Tenant\Resources\ReviewResource\Support\InteractsWithReviewSectionTabs;
use App\Models\ReviewImportSource;
use App\Models\User;
use App\Reviews\Import\ReviewImportSourceStatus;
use App\Services\Reviews\Imports\ReviewSourceDetector;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/** Страница зарегистрирована на {@see ReviewResource} (nested URL); модель записи — {@see ReviewImportSource}. */
final class CreateReviewImportSource extends CreateRecord
{
    use InteractsWithReviewSectionTabs;

    protected static string $resource = ReviewResource::class;

    protected static ?string $title = 'Добавить источник';

    protected static bool $canCreateAnother = false;

    protected function reviewSectionActiveTab(): string
    {
        return 'sources';
    }

    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('create', ReviewImportSource::class), 403);
    }

    public function getModel(): string
    {
        return ReviewImportSource::class;
    }

    public function form(Schema $schema): Schema
    {
        return ReviewImportSourceResource::form($schema);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['provider'] ?? '') === 'auto') {
            $data['provider'] = ReviewSourceDetector::providerFromUrl((string) ($data['source_url'] ?? ''));
        }

        $data['created_by'] = Auth::id();
        $tenant = currentTenant();
        abort_unless($tenant !== null, 403);

        $data['tenant_id'] = $tenant->id;

        if (($data['provider'] ?? '') === 'two_gis') {
            $data['status'] = ReviewImportSourceStatus::UNSUPPORTED;
        } elseif (($data['provider'] ?? '') === 'yandex_maps') {
            $data['status'] = ReviewImportSourceStatus::NEEDS_AUTH;
        } else {
            $data['status'] = ReviewImportSourceStatus::DRAFT;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ListReviewImportSources::getUrl();
    }
}
