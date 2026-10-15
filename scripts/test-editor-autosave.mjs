import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

// Serve only this fixture and the actual app script; this test never contacts DearYou.
const script = await readFile(new URL('../backend/public/assets/dearyou/app.js', import.meta.url), 'utf8');
const html = `<!doctype html><html><body>
<form data-letter-editor-form data-autosave-key="qa-editor-regression">
  <input type="hidden" name="_token" value="fixture-csrf">
  <input name="title" value="Original title">
  <input type="hidden" name="allow_response" value="0">
  <label><input type="checkbox" id="allow" name="allow_response" value="1" checked>Allow note</label>
  <label><input type="radio" name="envelope_style" value="classic" checked>Classic</label>
  <label><input type="radio" name="envelope_style" value="rounded">Rounded</label>
  <span data-draft-autosave-status></span>
</form>
<script src="/app.js"></script>
</body></html>`;
const launchOptions = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
  ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH }
  : process.platform === 'win32' ? { channel: 'msedge' } : {};
const browser = await chromium.launch({ headless: true, ...launchOptions });
try {
  const context = await browser.newContext();
  await context.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.origin !== 'https://qa.dearyou.invalid') return route.abort();
    if (url.pathname === '/app.js') return route.fulfill({ contentType: 'text/javascript', body: script });
    return route.fulfill({ contentType: 'text/html', body: html });
  });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('https://qa.dearyou.invalid/editor');

  for (const allowNote of [false, true]) {
    const title = `Draft with note ${allowNote ? 'enabled' : 'disabled'}`;
    await page.locator('#allow').setChecked(allowNote);
    await page.locator('[name="envelope_style"][value="rounded"]').check();
    await page.locator('[name="title"]').fill(title);
    await page.waitForFunction(expected => {
      const saved = JSON.parse(localStorage.getItem('qa-editor-regression') || 'null');
      return saved?.data?.title === expected;
    }, title);
    await page.reload();
    await page.locator('[data-draft-restore]').click();
    assert.equal(await page.locator('#allow').isChecked(), allowNote, 'Optional-note setting must survive draft restoration');
    assert.equal(await page.locator('[name="title"]').inputValue(), title);
    assert.equal(await page.locator('[name="envelope_style"][value="rounded"]').isChecked(), true);
  }

  assert.deepEqual(errors, [], 'The actual editor script should run without browser errors');
  console.log('PASS: unchecked/checked note preference, text, and radio choices survive draft restoration. No live requests.');
} finally {
  await browser.close();
}
