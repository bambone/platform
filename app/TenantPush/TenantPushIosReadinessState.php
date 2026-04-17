<?php

declare(strict_types=1);

namespace App\TenantPush;

/**
 * iOS / PWA readiness for web push (plan § iOS / PWA readiness).
 */
enum TenantPushIosReadinessState: string
{
    /** Desktop / Android / non‑Safari mobile — iOS-specific steps not required. */
    case NotApplicable = 'not_applicable';
    /** iOS/iPadOS before 16.4 — web push not available. */
    case IosNotSupported = 'ios_not_supported';
    /** iOS 16.4+ in browser tab — user should Add to Home Screen and open from icon. */
    case IosReadyButNotInstalled = 'ios_ready_but_not_installed';
    /** Cookie / client hint: site opened as installed PWA (standalone). */
    case IosInstalledReadyForPrompt = 'ios_installed_ready_for_prompt';
}
