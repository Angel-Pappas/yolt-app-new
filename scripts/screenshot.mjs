// Screenshot app pages from the DEV checkout (fake DemoSeeder data, never live), so
// a change can be looked at before it ships. Usage:
//
//   pnpm run shots /transactions "/transactions?balance=1"   [--mobile] [--dark]
//
// Starts `php artisan serve` on a spare port if needed, logs in as the demo user
// (session cached in storage/screenshots/.auth.json), and writes one PNG per path
// to storage/screenshots/, printing each file path. Needs `pnpm run build` first.
import { spawn } from 'node:child_process';
import { existsSync, mkdirSync } from 'node:fs';
import { chromium } from 'playwright-core';

const PORT = 8123;
const BASE = `http://127.0.0.1:${PORT}`;
const OUT = 'storage/screenshots';
const AUTH = `${OUT}/.auth.json`;

const args = process.argv.slice(2);
const mobile = args.includes('--mobile');
const dark = args.includes('--dark');
const paths = args.filter((a) => !a.startsWith('--'));
if (paths.length === 0) paths.push('/transactions');

mkdirSync(OUT, { recursive: true });

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
    for (let i = 0; i < 50 && !(await up()); i++) {
        await new Promise((r) => setTimeout(r, 200));
    }
}

const browser = await chromium.launch();
try {
    const context = await browser.newContext({
        viewport: mobile
            ? { width: 390, height: 844 }
            : { width: 1440, height: 900 },
        deviceScaleFactor: 1,
        colorScheme: dark ? 'dark' : 'light',
        storageState: existsSync(AUTH) ? AUTH : undefined,
    });
    const page = await context.newPage();

    await page.goto(`${BASE}/`);
    if (page.url().includes('/login')) {
        await page.fill('input[name="email"]', 'demo@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((u) => !u.pathname.startsWith('/login'));
        await context.storageState({ path: AUTH });
    }

    for (const path of paths) {
        await page.goto(`${BASE}${path}`);
        await page.waitForLoadState('networkidle');
        const slug =
            path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home';
        const file = `${OUT}/${slug}${mobile ? '-mobile' : ''}${dark ? '-dark' : ''}.png`;
        await page.screenshot({ path: file });
        console.log(file);
    }
} finally {
    await browser.close();
    server?.kill();
}
