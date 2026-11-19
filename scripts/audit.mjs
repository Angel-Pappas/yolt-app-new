// UI audit of the DEV app (fake DemoSeeder data, never live): visits every page on a
// laptop-sized and a phone-sized screen, opens every dialog, menu and searchable
// picker it can reach, and reports what a person would trip over:
//
// - the page scrolling sideways, or a table wider than its box;
// - a dialog, menu or picker reaching past the screen, or a dialog whose content
//   is cut off without scrolling, or whose buttons aren't visible;
// - a picker list that doesn't scroll with the mouse wheel;
// - text cut off where nothing lets you read the rest;
// - JavaScript errors and failed requests.
//
//   pnpm run audit            (all pages)       pnpm run audit /transactions /wallets
//
// It clicks Add/Edit-like buttons, row action menus and rows; things it changes are
// demo data (reseed with `php artisan migrate:fresh --seed --seeder=DemoSeeder`).
// Screenshots of each problem go to storage/screenshots/audit/. Exits 1 on findings.
import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, rmSync } from 'node:fs';
import { chromium } from 'playwright-core';

const PORT = Number(process.env.AUDIT_PORT ?? 8123);
const BASE = `http://127.0.0.1:${PORT}`;
const OUT = 'storage/screenshots/audit';
// Ids of demo rows whose own pages are visited (an employee, a customer, …).
const IDS = JSON.parse(
    process.env.AUDIT_IDS ??
        execFileSync('php', [
            'artisan',
            'tinker',
            '--execute',
            `echo json_encode([
                'employee' => App\\Models\\Entity::where('type', 'employee')->value('id'),
                'customer' => App\\Models\\Entity::where('type', 'customer')->value('id'),
                'category' => App\\Models\\Category::value('id'),
                'project' => class_exists('App\\Models\\Project') ? App\\Models\\Project::value('id') : null,
                'vat' => App\\Models\\VatRate::value('id'),
                'wallet' => App\\Models\\Wallet::value('id'),
            ]);`,
        ])
            .toString()
            .trim(),
);

const DEFAULT_PATHS = [
    '/',
    '/transactions',
    `/transactions?balance=${IDS.wallet ?? 1}`,
    '/wallets',
    '/bank-import?tab=statements',
    '/bank-import?tab=work',
    '/bank-import?tab=not-matched',
    '/planning/income',
    '/projects',
    `/projects/${IDS.project ?? 1}`,
    '/projection',
    '/planning/recurring',
    '/planning/one-offs',
    '/agreements',
    '/de-minimis',
    '/entities',
    '/entities/customers',
    '/entities/suppliers',
    '/entities/contractors',
    '/entities/employees',
    '/entities/shareholders',
    '/entities/state',
    `/entities/${IDS.employee ?? 1}`,
    `/entities/${IDS.customer ?? 1}`,
    '/planning/taxes',
    '/planning/taxes/vat',
    '/planning/taxes/withheld',
    '/planning/taxes/fmy',
    '/planning/taxes/efka',
    '/planning/taxes/income',
    '/configuration',
    '/configuration/categories',
    `/configuration/categories/${IDS.category ?? 1}`,
    '/configuration/vat-rates',
    `/configuration/vat-rates/${IDS.vat ?? 1}`,
    '/configuration/withheld-tax-rates',
    '/configuration/public-holidays',
    '/configuration/tax-rules',
    '/configuration/users',
    '/settings/profile',
    '/settings/security',
    '/settings/appearance',
];

const paths = process.argv.slice(2).filter((a) => a.startsWith('/'));
const VIEWPORTS = [
    { name: 'laptop', width: 1280, height: 720 },
    { name: 'phone', width: 390, height: 844 },
];
// Buttons that open a form or a choice — never ones that delete or submit.
const OPENERS =
    /^(add|new|\+|edit|upload|download|reconcile|invoice|create|change|set|pick|choose|show all|more|filter|open)/i;
const DANGER =
    /delete|remove|unpair|take .* out|log ?out|disable|revoke|deactivate|regenerate|reset|accept|apply|add fee|confirm/i;

async function up() {
    try {
        return (await fetch(`${BASE}/login`)).ok;
    } catch {
        return false;
    }
}

let server = null;
if (!(await up())) {
    server = spawn('php', ['artisan', 'serve', `--port=${PORT}`], {
        stdio: 'ignore',
    });
    for (let i = 0; i < 50 && !(await up()); i++)
        await new Promise((r) => setTimeout(r, 200));
}

rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });
const findings = [];
let shot = 0;

function report(page, vp, where, problem) {
    const key = `${vp.name} ${where}: ${problem}`;
    if (findings.some((f) => f.key === key)) return;
    findings.push({ key, vp: vp.name, where, problem });
    const file = `${OUT}/${String(++shot).padStart(3, '0')}.png`;
    return page
        .screenshot({ path: file })
        .then(() => {
            findings[findings.length - 1].shot = file;
        })
        .catch(() => {});
}

/** Problems with the layout of `root` (the page, or an open dialog/menu). */
async function layout(page, selector) {
    return page.evaluate((selector) => {
        const out = [];
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const doc = document.scrollingElement;
        const roots = selector
            ? [...document.querySelectorAll(selector)]
            : [document.body];
        if (!selector && doc.scrollWidth > doc.clientWidth + 1) {
            out.push(
                `page scrolls sideways (${doc.scrollWidth}px wide in ${doc.clientWidth}px)`,
            );
        }
        const name = (el) =>
            (el.getAttribute('aria-label') || el.textContent || el.tagName)
                .trim()
                .replace(/\s+/g, ' ')
                .slice(0, 40);
        for (const root of roots) {
            const r = root.getBoundingClientRect();
            if (selector) {
                if (
                    r.top < -1 ||
                    r.left < -1 ||
                    r.bottom > vh + 1 ||
                    r.right > vw + 1
                ) {
                    out.push(
                        `${root.getAttribute('role') || 'popup'} "${name(root).slice(0, 30)}" reaches past the screen (${Math.round(r.left)},${Math.round(r.top)} → ${Math.round(r.right)},${Math.round(r.bottom)})`,
                    );
                }
                const cs = getComputedStyle(root);
                if (
                    root.scrollHeight > root.clientHeight + 1 &&
                    !/(auto|scroll)/.test(cs.overflowY)
                ) {
                    out.push(
                        `${root.getAttribute('role')} content is cut off and can't scroll`,
                    );
                }
                if (
                    root.scrollWidth > root.clientWidth + 1 &&
                    !/(auto|scroll)/.test(cs.overflowX)
                ) {
                    out.push(
                        `${root.getAttribute('role')} content is wider than it`,
                    );
                }
            }
            // Buttons that should be reachable: visible and not covered.
            for (const b of root.querySelectorAll(
                'button, a[href], input, [role=combobox]',
            )) {
                const br = b.getBoundingClientRect();
                if (br.width === 0 || br.height === 0) continue;
                const cs = getComputedStyle(b);
                if (
                    cs.visibility === 'hidden' ||
                    b.closest('[aria-hidden=true]') ||
                    b.closest('.sr-only')
                )
                    continue;
                // Off to the side of a box that can't scroll sideways = unreachable.
                let p = b.parentElement;
                while (p && p !== root.parentElement) {
                    const pcs = getComputedStyle(p);
                    if (/(auto|scroll)/.test(pcs.overflowX)) break; // reachable by scrolling it
                    if (/(hidden|clip)/.test(pcs.overflowX)) {
                        const pr = p.getBoundingClientRect();
                        if (br.right > pr.right + 2 || br.left < pr.left - 2) {
                            out.push(
                                `"${name(b)}" is cut off by its box (${p.className.toString().slice(0, 60)})`,
                            );
                        }
                        break;
                    }
                    p = p.parentElement;
                }
                if (br.right > vw + 1 || br.left < -1) {
                    // Inside a box that scrolls sideways it is reachable (a wide table
                    // is reported once, as the table).
                    let sc = b.parentElement;
                    while (
                        sc &&
                        sc !== document.body &&
                        !/(auto|scroll)/.test(getComputedStyle(sc).overflowX)
                    )
                        sc = sc.parentElement;
                    if (!sc || sc === document.body)
                        out.push(`"${name(b)}" is off the screen sideways`);
                }
            }
            // Tables wider than their box: columns hidden off to the side.
            for (const box of root.querySelectorAll(
                '[data-slot=table-container]',
            )) {
                const t = box.querySelector('table');
                if (
                    t &&
                    t.getBoundingClientRect().width >
                        box.parentElement.getBoundingClientRect().width + 2
                ) {
                    out.push(
                        `table is wider than its box (${Math.round(t.getBoundingClientRect().width)} in ${Math.round(box.parentElement.getBoundingClientRect().width)}px): columns hidden sideways`,
                    );
                }
            }
            // Text cut off with no way to read it (no title, not a select value).
            for (const el of root.querySelectorAll('*')) {
                if (el.children.length > 0 || !el.textContent.trim()) continue;
                if (el.getBoundingClientRect().width <= 1) continue; // screen-reader only
                const cs = getComputedStyle(el);
                const clipped =
                    (cs.textOverflow === 'ellipsis' ||
                        /(hidden|clip)/.test(cs.overflowX)) &&
                    el.scrollWidth > el.clientWidth + 1;
                if (
                    clipped &&
                    !el.closest('[title]') &&
                    !el.closest('[data-slot=select-value]') &&
                    !el.closest('.sr-only') &&
                    !el.closest('[data-slot=sidebar]')
                ) {
                    out.push(
                        `text cut off: "${el.textContent.trim().slice(0, 40)}"`,
                    );
                }
            }
        }
        return [...new Set(out)];
    }, selector);
}

/** Every searchable picker in the open dialog: its list fits and scrolls by wheel. */
async function pickers(page, vp, where) {
    const combos = await page
        .locator('[role=dialog] button[role=combobox][aria-expanded]')
        .elementHandles();
    for (const combo of combos) {
        if (!(await combo.isVisible())) continue;
        await combo.click().catch(() => {});
        await page.waitForTimeout(250);
        const list = page.locator('[data-slot=command-list]').last();
        if (await list.count()) {
            for (const p of await layout(page, '[data-slot=popover-content]'))
                await report(page, vp, where, `picker: ${p}`);
            const before = await list.evaluate((e) => [
                e.scrollTop,
                e.scrollHeight,
                e.clientHeight,
            ]);
            if (before[1] > before[2] + 4) {
                const box = await list.boundingBox();
                await page.mouse.move(
                    box.x + box.width / 2,
                    box.y + box.height / 2,
                );
                await page.mouse.wheel(0, 200);
                await page.waitForTimeout(200);
                if ((await list.evaluate((e) => e.scrollTop)) === 0)
                    await report(
                        page,
                        vp,
                        where,
                        'picker list does not scroll with the mouse wheel',
                    );
            }
            // Choosing Payroll shows the payroll fields: the form must still fit.
            const payroll = page
                .locator('[data-slot=command-item]')
                .filter({ hasText: /^Payroll$/ });
            if (await payroll.count()) {
                await payroll.first().click();
                await page.waitForTimeout(300);
                for (const p of await layout(page, '[role=dialog]'))
                    await report(page, vp, `${where} (Payroll)`, p);
                await footerVisible(page, vp, `${where} (Payroll)`);
                continue;
            }
        }
        await page.keyboard.press('Escape');
        await page.waitForTimeout(150);
    }
}

/** The dialog's last buttons (its Save/Cancel) are on screen without scrolling. */
async function footerVisible(page, vp, where) {
    const ok = await page.evaluate(() => {
        const d = [...document.querySelectorAll('[role=dialog]')].pop();
        if (!d) return true;
        const buttons = [
            ...d.querySelectorAll(
                'button[type=submit], [data-slot=dialog-footer] button',
            ),
        ];
        return buttons.every((b) => {
            const r = b.getBoundingClientRect();
            if (r.width === 0) return true;
            const el = document.elementFromPoint(
                r.left + r.width / 2,
                r.top + r.height / 2,
            );
            return (
                el &&
                (el === b || b.contains(el)) &&
                r.bottom <= window.innerHeight
            );
        });
    });
    if (!ok)
        await report(
            page,
            vp,
            where,
            "dialog's buttons are not visible without scrolling",
        );
}

async function closeAll(page) {
    for (let i = 0; i < 3; i++) {
        if (
            !(await page
                .locator(
                    '[role=dialog], [role=menu], [data-slot=popover-content]',
                )
                .count())
        )
            return;
        await page.keyboard.press('Escape');
        await page.waitForTimeout(150);
    }
}

const opened = [];
async function checkOpen(page, vp, where) {
    await page.waitForTimeout(350);
    for (const [sel, kind] of [
        ['[role=dialog]', 'dialog'],
        ['[role=menu]', 'menu'],
        ['[data-slot=popover-content]', 'popup'],
    ]) {
        if (await page.locator(sel).count()) {
            opened.push(`${vp.name} ${where} [${kind}]`);
            break;
        }
    }
    if (await page.locator('[role=dialog]').count()) {
        for (const p of await layout(page, '[role=dialog]'))
            await report(page, vp, where, p);
        await footerVisible(page, vp, where);
        // A form dialog must survive a stray click beside it (typed data is kept).
        if (await page.locator('[role=dialog] form').count()) {
            await page.mouse.click(3, 3);
            await page.waitForTimeout(250);
            if (!(await page.locator('[role=dialog] form').count())) {
                await report(
                    page,
                    vp,
                    where,
                    'a click beside the form closes it (typed data is lost)',
                );
                return;
            }
        }
        await pickers(page, vp, where);
    } else if (await page.locator('[role=menu]').count()) {
        for (const p of await layout(page, '[role=menu]'))
            await report(page, vp, where, `menu: ${p}`);
    } else if (await page.locator('[data-slot=popover-content]').count()) {
        for (const p of await layout(page, '[data-slot=popover-content]'))
            await report(page, vp, where, `popup: ${p}`);
    }
}

const browser = await chromium.launch();
try {
    for (const vp of VIEWPORTS) {
        const context = await browser.newContext({
            viewport: { width: vp.width, height: vp.height },
            deviceScaleFactor: 1,
        });
        const page = await context.newPage();
        page.on('dialog', (d) => d.dismiss()); // window.confirm → "no"
        let current = '';
        page.on('pageerror', (e) =>
            findings.push({
                key: `${vp.name} ${current}: JS error ${e.message}`,
                vp: vp.name,
                where: current,
                problem: `JS error: ${e.message}`,
            }),
        );
        page.on(
            'console',
            (m) =>
                m.type() === 'error' &&
                !/favicon|Failed to load resource/.test(m.text()) &&
                findings.push({
                    key: `${vp.name} ${current}: console ${m.text()}`,
                    vp: vp.name,
                    where: current,
                    problem: `console error: ${m.text().slice(0, 150)}`,
                }),
        );
        page.on(
            'response',
            (r) =>
                r.status() >= 400 &&
                r.status() !== 409 &&
                findings.push({
                    key: `${vp.name} ${current}: ${r.status()} ${r.url()}`,
                    vp: vp.name,
                    where: current,
                    problem: `request failed: ${r.status()} ${r.url()}`,
                }),
        );

        await page.goto(`${BASE}/login`);
        await page.fill('input[name=email]', 'demo@example.com');
        await page.fill('input[name=password]', 'password');
        await page.click('button[type=submit]');
        await page.waitForURL((u) => !u.pathname.includes('login'));

        for (const path of paths.length ? paths : DEFAULT_PATHS) {
            current = path;
            await page.goto(`${BASE}${path}`);
            await page.waitForLoadState('networkidle').catch(() => {});
            for (const p of await layout(page, null))
                await report(page, vp, path, p);

            const main = page
                .locator('main, [data-slot=sidebar-inset]')
                .first();
            const scope = (await main.count()) ? main : page.locator('body');
            // Buttons that open something: by name, menus, and popups.
            // Located afresh each time: opening and closing things re-renders the
            // page, which would leave stored handles pointing at old elements.
            const total = Math.min(
                await scope.locator('button:visible').count(),
                120,
            );
            const seen = new Set();
            for (let n = 0; n < total; n++) {
                const b = await scope
                    .locator('button:visible')
                    .nth(n)
                    .elementHandle({ timeout: 2000 })
                    .catch(() => null);
                if (!b) continue;
                const label = (
                    (await b.getAttribute('aria-label')) ||
                    (await b.textContent()) ||
                    ''
                )
                    .trim()
                    .replace(/\s+/g, ' ');
                const popup = await b.getAttribute('aria-haspopup');
                const key = `${label}|${popup}`;
                if (
                    seen.has(key) ||
                    DANGER.test(label) ||
                    (!popup && !OPENERS.test(label))
                )
                    continue;
                if (
                    (await b.getAttribute('type')) === 'submit' ||
                    (await b.getAttribute('role')) === 'checkbox'
                )
                    continue;
                seen.add(key);
                if (!(await b.isVisible().catch(() => false))) continue;
                await b.click({ timeout: 2000 }).catch(() => {});
                await checkOpen(page, vp, `${path} › ${label || popup}`);
                // Menu items that open a dialog (row actions): Reconcile, Invoice month…
                const items = await page
                    .locator('[role=menuitem]')
                    .elementHandles();
                if (items.length && popup === 'menu') {
                    const names = [];
                    for (const it of items)
                        names.push(((await it.textContent()) || '').trim());
                    await closeAll(page);
                    for (const n of names) {
                        if (!n || DANGER.test(n) || seen.has(`menu:${n}`))
                            continue;
                        seen.add(`menu:${n}`);
                        await b.click({ timeout: 2000 }).catch(() => {});
                        await page.waitForTimeout(200);
                        const item = page
                            .locator('[role=menuitem]')
                            .filter({ hasText: n })
                            .first();
                        if (!(await item.count())) {
                            await closeAll(page);
                            continue;
                        }
                        const before = page.url();
                        await item.click({ timeout: 2000 }).catch(() => {});
                        await checkOpen(page, vp, `${path} › ⋯ › ${n}`);
                        await closeAll(page);
                        if (page.url() !== before)
                            await page.goto(`${BASE}${path}`);
                    }
                }
                await closeAll(page);
                if (!page.url().startsWith(`${BASE}${path.split('?')[0]}`)) {
                    await page.goto(`${BASE}${path}`);
                    await page.waitForLoadState('networkidle').catch(() => {});
                }
            }
            // A row opens its edit dialog (or its page).
            const row = scope.locator('tbody tr').first();
            if (await row.count()) {
                const before = page.url();
                await row
                    .locator('td')
                    .nth(2)
                    .click({ timeout: 2000, force: true })
                    .catch(() => {});
                await checkOpen(page, vp, `${path} › row`);
                await closeAll(page);
                if (page.url() !== before) {
                    current = page.url().replace(BASE, '');
                    for (const p of await layout(page, null))
                        await report(page, vp, current, p);
                }
            }
        }
        await context.close();
    }
} finally {
    await browser.close();
    server?.kill();
}

if (process.env.AUDIT_VERBOSE)
    for (const o of opened) console.log(`opened: ${o}`);
console.log(`${opened.length} dialogs/menus/popups opened and checked.`);
const unique = [...new Map(findings.map((f) => [f.key, f])).values()];
for (const f of unique)
    console.log(
        `[${f.vp}] ${f.where} — ${f.problem}${f.shot ? `  (${f.shot})` : ''}`,
    );
console.log(`${unique.length} finding(s).`);
process.exit(unique.length ? 1 : 0);
