/**
 * Guided site setup (opt-in E2E): контракт DOM, editor, payload.
 *
 * Enable with:
 *   PLAYWRIGHT_E2E=1
 *   PLAYWRIGHT_BASE_URL=https://your-tenant-host.test
 *   PLAYWRIGHT_ADMIN_EMAIL=...
 *   PLAYWRIGHT_ADMIN_PASSWORD=...
 *   PLAYWRIGHT_GUIDED_HOME_EDIT_PATH=/admin/pages/{id}/edit?relation=0
 *
 * Then: npx playwright install chromium && npm run test:e2e -- e2e/guided-site-setup.spec.ts
 *
 * Полные регрессии client-side веток (чужой editor → переоткрытие нужного, debounce attempts,
 * prefer-primary без лишнего startEdit, fallback в document при открытом editor) требуют
 * активной guided-сессии на шаге pages.home.hero_title и стабильных фикстур;
 * расширьте сценарии при появлении фикстур или отдельного стенда.
 */
import { test, expect } from '@playwright/test';

const enabled =
    process.env.PLAYWRIGHT_E2E === '1' &&
    !!process.env.PLAYWRIGHT_BASE_URL &&
    !!process.env.PLAYWRIGHT_ADMIN_EMAIL &&
    !!process.env.PLAYWRIGHT_ADMIN_PASSWORD;

const homeEditPath = process.env.PLAYWRIGHT_GUIDED_HOME_EDIT_PATH?.trim();

test.describe('Guided site setup (opt-in E2E)', () => {
    test.beforeEach(() => {
        test.skip(
            !enabled,
            'Set PLAYWRIGHT_E2E=1, PLAYWRIGHT_BASE_URL, PLAYWRIGHT_ADMIN_EMAIL, PLAYWRIGHT_ADMIN_PASSWORD',
        );
    });

    test.beforeEach(async ({ page }) => {
        await page.goto('/admin/login');
        await page.getByLabel(/email|почт/i).fill(process.env.PLAYWRIGHT_ADMIN_EMAIL!);
        await page.getByLabel(/password|парол/i).fill(process.env.PLAYWRIGHT_ADMIN_PASSWORD!);
        await page.locator('button[type="submit"]').first().click();
        await page.waitForURL(/\/admin/);
    });

    test('E: payload root — последний [data-tenant-site-setup-root] и script payload', async ({ page }) => {
        test.skip(!homeEditPath, 'Set PLAYWRIGHT_GUIDED_HOME_EDIT_PATH to home page edit URL with relation=0');
        await page.goto(homeEditPath!);
        const roots = page.locator('[data-tenant-site-setup-root]');
        const count = await roots.count();
        if (count === 0) {
            test.skip(true, 'Нет guided overlay: включите сессию быстрого запуска на этом тенанте');
        }
        const lastRoot = roots.nth(count - 1);
        await expect(lastRoot.locator('[data-tenant-site-setup="payload"]')).toBeAttached();
        const txt = await lastRoot.locator('[data-tenant-site-setup="payload"]').textContent();
        expect(txt).toBeTruthy();
        const payload = JSON.parse(txt!);
        expect(payload).toHaveProperty('page_builder_auto_open');
        expect(payload.page_builder_auto_open).toHaveProperty('enabled');
    });

    test('page builder editor exposes stable data-setup-editor-section-id when editing a block', async ({ page }) => {
        test.skip(!homeEditPath, 'Set PLAYWRIGHT_GUIDED_HOME_EDIT_PATH');
        await page.goto(homeEditPath!);
        const editBtn = page.getByRole('button', { name: /^редактировать$/i }).first();
        try {
            await expect(editBtn).toBeVisible({ timeout: 25_000 });
        } catch {
            test.skip(true, 'На странице нет блока с кнопкой «Редактировать»');
        }
        await editBtn.click();
        const editor = page.locator('.page-sections-builder-editor').first();
        await expect(editor).toBeVisible({ timeout: 20_000 });
        const sectionId = await editor.getAttribute('data-setup-editor-section-id');
        expect(sectionId, 'data-setup-editor-section-id задан в Blade для контракта с tenant-admin-site-setup.js').toBeTruthy();
        expect(sectionId!.trim()).toMatch(/^\d+$/);
    });

    /**
     * Регрессии guided JS (заготовка под стенд с активной сессией hero_title):
     * - открыт чужой блок → startEdit(heroSectionId) снова;
     * - повторный init < 900 ms после startEdit → attempts не растут;
     * - нужный editor уже открыт → prefer-primary без второго startEdit;
     * - data-setup-section-type на списке виден при открытом slide-over.
     * Включите при готовности фикстур: PLAYWRIGHT_GUIDED_HERO_REGRESSION=1
     */
    test('guided hero regression (opt-in)', async () => {
        test.skip(
            process.env.PLAYWRIGHT_GUIDED_HERO_REGRESSION !== '1',
            'Set PLAYWRIGHT_GUIDED_HERO_REGRESSION=1 and extend with guided session + assertions',
        );
    });
});
