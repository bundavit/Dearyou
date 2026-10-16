import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

// Use the shipped script against synthetic DOM only; every request is intercepted.
const script = await readFile(new URL('../backend/public/assets/dearyou/app.js', import.meta.url), 'utf8');
const origin = 'https://qa.dearyou.invalid';
const key = 'qa-ui-recovery';
const html = `<!doctype html><html><body>
<div><input id="share" value="https://qa.dearyou.invalid/l/synthetic"><button type="button" data-copy="#share">Copy link</button></div>
<div class="password-field"><input type="password" data-password-input value="synthetic"><button type="button" data-password-toggle aria-label="Show password">Show password</button></div>
<form method="post" action="/synthetic-save" data-letter-editor-form data-autosave-key="${key}">
  <input name="title" value="Original title"><textarea name="body">Original body</textarea>
  <span data-draft-autosave-status></span><button type="submit" id="save">Save letter</button>
</form><script src="/app.js"></script></body></html>`;
const launchOptions = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
  ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH }
  : process.platform === 'win32' ? { channel: 'msedge' } : {};
const browser = await chromium.launch({ headless: true, ...launchOptions });
let passed = 0;

async function fixture(name, run, init) {
  const context = await browser.newContext();
  const errors = [];
  const unexpectedRequests = [];
  if (init) await context.addInitScript(init, key);
  await context.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (url.origin !== origin || request.method() !== 'GET') {
      unexpectedRequests.push(`${request.method()} ${url.origin}${url.pathname}`);
      return route.abort();
    }
    return route.fulfill(url.pathname === '/app.js'
      ? { contentType: 'text/javascript', body: script }
      : { contentType: 'text/html', body: html });
  });
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  try {
    await page.goto(`${origin}/editor`);
    await run(page);
    assert.deepEqual(errors, [], `${name}: no uncaught browser errors`);
    assert.deepEqual(unexpectedRequests, [], `${name}: no external or mutating requests`);
    passed++;
    console.log(`PASS: ${name}`);
  } finally {
    await context.close();
  }
}

async function expectStorageWarning(page) {
  await page.waitForFunction(() => document.querySelector('[data-draft-autosave-status]').textContent.includes('autosave is unavailable'));
  assert.equal(await page.locator('[data-draft-autosave-status]').getAttribute('role'), 'status');
}

async function expectOtherControlsWork(page) {
  await page.getByRole('button', { name: 'Show password', exact: true }).click();
  assert.equal(await page.locator('[data-password-input]').getAttribute('type'), 'text');
  await page.getByRole('button', { name: 'Hide password', exact: true }).click();
  assert.equal(await page.locator('[data-password-input]').getAttribute('type'), 'password');
  await page.locator('form').evaluate(form => {
    form.addEventListener('submit', event => event.preventDefault());
    form.requestSubmit(document.querySelector('#save'));
  });
  assert.equal(await page.locator('#save').isDisabled(), true, 'The later submit listener still runs');
  assert.equal(await page.locator('#save').textContent(), 'Working...');
}

try {
  await fixture('Clipboard keyboard fallback, retry success, and subsequent failure', async page => {
    const copy = page.locator('[data-copy]');
    await copy.focus();
    await copy.press('Enter');
    await page.waitForFunction(() => document.querySelector('[data-copy-status]')?.textContent.includes('manually'));
    assert.equal(await page.locator('[data-copy-status]').getAttribute('role'), 'status');
    assert.equal(await page.locator('#share').evaluate(input => document.activeElement === input && input.selectionStart === 0 && input.selectionEnd === input.value.length), true);
    assert.equal(await copy.textContent(), 'Copy link');
    await page.evaluate(() => window.clipboardAllowed = true);
    await copy.click();
    await page.getByRole('button', { name: 'Copied', exact: true }).waitFor();
    assert.equal(await page.locator('[data-copy-status]').textContent(), 'Link copied.');
    assert.deepEqual(await page.evaluate(() => window.clipboardWrites), [`${origin}/l/synthetic`]);
    await page.evaluate(() => window.clipboardAllowed = false);
    await copy.click();
    await page.getByRole('button', { name: 'Copy link', exact: true }).waitFor();
    assert.match(await page.locator('[data-copy-status]').textContent(), /manually/);
    await expectOtherControlsWork(page);
  }, () => {
    window.clipboardAllowed = false;
    window.clipboardWrites = [];
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: async value => {
      if (!window.clipboardAllowed) throw new DOMException('Synthetic denial', 'NotAllowedError');
      window.clipboardWrites.push(value);
    } } });
  });

  await fixture('Missing Clipboard API provides a manual selection', async page => {
    await page.locator('[data-copy]').click();
    await page.waitForFunction(() => document.querySelector('[data-copy-status]')?.textContent.includes('manually'));
    assert.equal(await page.locator('#share').evaluate(input => document.activeElement === input && input.selectionEnd === input.value.length), true);
  }, () => Object.defineProperty(navigator, 'clipboard', { value: undefined }));

  for (const operation of ['getter', 'read']) {
    await fixture(`Blocked localStorage ${operation} leaves editing and other controls usable`, async page => {
      await expectStorageWarning(page);
      await page.locator('[name="title"]').fill('Still editable');
      assert.equal(await page.locator('[name="title"]').inputValue(), 'Still editable');
      await expectOtherControlsWork(page);
    }, operation === 'getter'
      ? () => Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('Synthetic denial', 'SecurityError'); } })
      : () => { Storage.prototype.getItem = () => { throw new DOMException('Synthetic denial', 'SecurityError'); }; });
  }

  await fixture('Autosave write failure reports a warning and preserves edited text', async page => {
    await page.locator('[name="title"]').fill('Do not lose this title');
    await page.locator('[name="body"]').fill('Do not lose this letter');
    await expectStorageWarning(page);
    assert.equal(await page.locator('[name="title"]').inputValue(), 'Do not lose this title');
    assert.equal(await page.locator('[name="body"]').inputValue(), 'Do not lose this letter');
    await expectOtherControlsWork(page);
  }, () => { Storage.prototype.setItem = () => { throw new DOMException('Synthetic capacity', 'QuotaExceededError'); }; });

  await fixture('Failed draft removal keeps the warning after Ignore', async page => {
    await page.locator('[data-draft-ignore]').click();
    await expectStorageWarning(page);
    assert.equal(await page.locator('[data-draft-restore]').count(), 0);
    assert.equal(await page.locator('[name="title"]').inputValue(), 'Original title');
    assert.notEqual(await page.evaluate(key => localStorage.getItem(key), key), null);
    await expectOtherControlsWork(page);
  }, key => {
    localStorage.setItem(key, JSON.stringify({ data: { title: 'Old draft' } }));
    Storage.prototype.removeItem = () => { throw new DOMException('Synthetic denial', 'SecurityError'); };
  });

  for (const value of ['{broken', 'null', '{"data":null}', '{"data":[]}']) {
    await fixture(`Malformed draft ${value} is discarded safely`, async page => {
      await page.evaluate(({ key, value }) => localStorage.setItem(key, value), { key, value });
      await page.reload();
      assert.equal(await page.evaluate(key => localStorage.getItem(key), key), null);
      assert.equal(await page.locator('[data-draft-restore]').count(), 0);
      assert.equal(await page.locator('[name="title"]').inputValue(), 'Original title');
    });
  }

  await fixture('Submitting clears the pending autosave without recreating the draft', async page => {
    await page.evaluate(key => localStorage.setItem(key, JSON.stringify({ data: { title: 'Earlier draft' } })), key);
    await page.locator('[name="title"]').fill('Pending save');
    await expectOtherControlsWork(page);
    await page.waitForTimeout(850); // Exceed the actual 650 ms debounce while navigation is prevented.
    assert.equal(await page.evaluate(key => localStorage.getItem(key), key), null);
  });

  console.log(`PASS: ${passed} offline UI recovery scenarios. No live requests or accounts.`);
} finally {
  await browser.close();
}
