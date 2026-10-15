import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { chromium } from 'playwright';

const root = new URL('../', import.meta.url);
const edge = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
    || (process.platform === 'win32' && existsSync(edge) ? edge : undefined);
const browser = await chromium.launch({ headless: true, executablePath });

try {
    const page = await browser.newPage();
    await page.route('**/*', route => route.abort());
    await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body class="public-page"><section class="opened-letter-scene"><article class="paper">
        <p class="letter-to">Dear recipient,</p><h1>A private letter</h1>
        <div class="letter-body">A useful long link: https://example.invalid/${'a'.repeat(100)}</div>
        <p class="letter-signoff">With care, sender</p>
        <div class="memory-card"><h3>A memory</h3><p>A memory caption</p></div>
        </article></section></body></html>`);
    await page.addStyleTag({ content: readFileSync(new URL('backend/public/vendor/bootstrap/css/bootstrap.min.css', root), 'utf8') });
    await page.addStyleTag({ content: readFileSync(new URL('backend/public/assets/dearyou/app.css', root), 'utf8') });

    for (const width of [390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        const result = await page.evaluate(() => {
            const body = document.querySelector('.letter-body');
            return {
                viewport: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                contentWidth: body.clientWidth,
                scrollWidth: body.scrollWidth,
            };
        });
        assert.ok(result.documentWidth <= width + 1, `Document overflows at ${width}px: ${JSON.stringify(result)}`);
        assert.ok(result.scrollWidth <= result.contentWidth + 1, `Letter text overflows at ${width}px: ${JSON.stringify(result)}`);
        console.log(`Public letter long URL fits ${width}px.`);
    }
} finally {
    await browser.close();
}
