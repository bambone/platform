<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Tenant\MagasExpertBootstrap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent onboarding for Sergei Magas tenant ({@see MagasExpertBootstrap}). Not chained from {@see \Database\Seeders\DatabaseSeeder}.
 */
final class TenantMagasBootstrapCommand extends Command
{
    protected $signature = 'tenant:magas:bootstrap
                            {--publish : Publish substantive pages (home, services hub, marketing mains) and matching SEO indexable; placeholders stay draft unless --allow-placeholder-content}
                            {--allow-placeholder-content : With --publish, also publish scaffold legal/terms, service detail stubs, and FAQs}
                            {--force-draft : When updating an existing tenant, re-apply bootstrap page statuses (draft/noindex when running without --publish)}
                            {--canonical-id= : Insert new tenant row with this primary key when the slot is free (omit for normal AUTO_INCREMENT)}';

    protected $description = 'Create missing bootstrap/demo content for tenant sergey-magas (expert_pr). Idempotent; does not overwrite existing sections/copy. Default: new scaffold is draft + noindex; existing published pages stay published unless you pass --force-draft and omit --publish.';

    public function handle(): int
    {
        $publish = (bool) $this->option('publish');
        $forceDraft = (bool) $this->option('force-draft');
        $allowPlaceholders = (bool) $this->option('allow-placeholder-content');

        if ($publish && $forceDraft) {
            $this->error('--force-draft cannot be combined with --publish.');

            return self::FAILURE;
        }

        if ($allowPlaceholders && ! $publish) {
            $this->warn('--allow-placeholder-content has no effect without --publish.');
        }

        $canonicalOpt = $this->option('canonical-id');
        $canonicalId = null;
        if ($canonicalOpt !== null && $canonicalOpt !== '') {
            if (! ctype_digit((string) $canonicalOpt) || (int) $canonicalOpt <= 0) {
                $this->error('--canonical-id must be a positive integer.');

                return self::FAILURE;
            }
            $canonicalId = (int) $canonicalOpt;
            if (DB::table('tenants')->whereKey($canonicalId)->exists()) {
                $this->warn("Requested canonical id {$canonicalId} is already occupied; AUTO_INCREMENT will be used.");
            }
        }

        MagasExpertBootstrap::run(
            $publish,
            $canonicalId,
            $allowPlaceholders,
            $forceDraft,
        );

        $suffix = $publish
            ? ('published routes + SEO where applicable'.($allowPlaceholders
                ? ' (including placeholders).'
                : ' (legal/service stubs + FAQ stay draft until --allow-placeholder-content).'))
            : 'draft/noindex scaffold + existing published pages untouched (use --force-draft to re-sync drafts).';

        $this->info(
            'Magas tenant bootstrap finished (slug '.MagasExpertBootstrap::SLUG.') — '
            .($canonicalId !== null ? 'requested insert id '.$canonicalId.' if free; ' : 'AUTO_INCREMENT insert when creating tenant; ')
            .$suffix
        );

        return self::SUCCESS;
    }
}
