import { chromium } from 'playwright';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const outputDir = path.join(root, 'screenshots');
const manifestPath = path.join(outputDir, 'manifest.json');

const viewport = { width: 1440, height: 900 };

async function loadConfig() {
  const raw = await readFile(path.join(root, 'scripts', 'screenshot-config.json'), 'utf8');
  return JSON.parse(raw);
}

async function login(page, baseUrl, email, password, loginPath = '/login') {
  await page.goto(`${baseUrl}${loginPath}`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.locator('form button.btn-dearyou').click();
  await page.waitForLoadState('networkidle');
}

async function capture(page, url, fileName, options = {}) {
  const target = url.startsWith('http') ? url : `${options.baseUrl}${url}`;
  await page.goto(target, { waitUntil: 'networkidle' });
  if (options.waitMs) {
    await page.waitForTimeout(options.waitMs);
  }
  const filePath = path.join(outputDir, fileName);
  await page.screenshot({ path: filePath, fullPage: true });
  return filePath;
}

async function main() {
  const config = await loadConfig();
  const { baseUrl } = config;

  await mkdir(outputDir, { recursive: true });
  await mkdir(path.join(outputDir, 'public'), { recursive: true });
  await mkdir(path.join(outputDir, 'creator'), { recursive: true });
  await mkdir(path.join(outputDir, 'admin'), { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const manifest = [];

  const record = (group, name, file) => {
    manifest.push({ group, name, file });
  };

  const makePublisher = (page) => async (group, name, url, fileName, opts = {}) => {
    await capture(page, url, fileName, { baseUrl, ...opts });
    record(group, name, fileName);
    console.log(`Captured ${fileName}`);
  };

  // Public pages (guest session)
  {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    const pub = makePublisher(page);

    await pub('public', 'Homepage', '/', 'public/01-homepage.png', { waitMs: 500 });
    await pub('public', 'Login', '/login', 'public/02-login.png');
    await pub('public', 'Register', '/register', 'public/03-register.png');
    await pub('public', 'Forgot password', '/forgot-password', 'public/04-forgot-password.png');
    await pub('public', 'Forgot password code', '/forgot-password/code', 'public/05-forgot-password-code.png');
    await pub('public', 'Reset password', '/reset-password?email=demo@dearyou.test&token=sample', 'public/06-reset-password.png');
    await pub('public', 'Admin login', '/admin/login', 'public/07-admin-login.png');
    if (config.publishedToken) {
      await pub('public', 'Public letter', `/l/${config.publishedToken}`, 'public/08-public-letter.png', { waitMs: 1200 });
    }
    await pub('public', '404 page', '/this-page-does-not-exist', 'public/09-404.png');

    await context.close();
  }

  // Creator workspace
  {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    const pub = makePublisher(page);

    await login(page, baseUrl, config.demo.email, config.demo.password);
    await pub('creator', 'Letters dashboard', '/letters', 'creator/01-letters-index.png');
    await pub('creator', 'Create letter', '/letters/create', 'creator/02-letters-create.png');
    if (config.letterIds?.demo?.[0]) {
      const id = config.letterIds.demo[0];
      await pub('creator', 'Letter detail', `/letters/${id}`, 'creator/03-letter-show.png');
      await pub('creator', 'Edit letter', `/letters/${id}/edit`, 'creator/04-letter-edit.png');
      await pub('creator', 'Letter preview', `/letters/${id}/preview`, 'creator/05-letter-preview.png', { waitMs: 800 });
    }
    await pub('creator', 'Inbox', '/inbox', 'creator/06-inbox.png');
    if (config.responseId) {
      await pub('creator', 'Response detail', `/responses/${config.responseId}`, 'creator/07-response-show.png');
    }
    await pub('creator', 'Account settings', '/account', 'creator/08-account.png');

    await context.close();
  }

  // Verify email page (unverified user session)
  {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    const pub = makePublisher(page);

    await login(page, baseUrl, config.unverified.email, config.unverified.password);
    await pub('creator', 'Verify email', '/verify-email', 'creator/09-verify-email.png');

    await context.close();
  }

  // Admin platform
  {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    const pub = makePublisher(page);

    await login(page, baseUrl, config.admin.email, config.admin.password, '/admin/login');
    await pub('admin', 'Platform dashboard', '/admin/platform', 'admin/01-platform-dashboard.png');
    await pub('admin', 'Legacy dashboard', '/admin/dashboard', 'admin/02-legacy-dashboard.png');
    await pub('admin', 'Letters index', '/admin/letters', 'admin/03-letters-index.png');
    await pub('admin', 'Create letter', '/admin/letters/create', 'admin/04-letters-create.png');
    const adminLetterId = config.letterIds?.admin ?? config.letterIds?.demo?.[1];
    if (adminLetterId) {
      await pub('admin', 'Letter detail', `/admin/letters/${adminLetterId}`, 'admin/05-letter-show.png');
      await pub('admin', 'Edit letter', `/admin/letters/${adminLetterId}/edit`, 'admin/06-letter-edit.png');
      await pub('admin', 'Letter preview', `/admin/letters/${adminLetterId}/preview`, 'admin/07-letter-preview.png', { waitMs: 800 });
    }
    await pub('admin', 'Inbox', '/admin/inbox', 'admin/08-inbox.png');
    if (config.responseId) {
      await pub('admin', 'Response detail', `/admin/responses/${config.adminResponseId ?? config.responseId}`, 'admin/09-response-show.png');
    }
    await pub('admin', 'Users index', '/admin/users', 'admin/10-users-index.png');
    if (config.userId) {
      await pub('admin', 'User detail', `/admin/users/${config.userId}`, 'admin/11-user-show.png');
    }
    await pub('admin', 'Platform settings', '/admin/settings', 'admin/12-settings.png');
    await pub('admin', 'Feedback index', '/admin/feedback', 'admin/13-feedback-index.png');
    if (config.feedbackId) {
      await pub('admin', 'Feedback detail', `/admin/feedback/${config.feedbackId}`, 'admin/14-feedback-show.png');
    }
    await pub('admin', 'Moderation index', '/admin/moderation/letters', 'admin/15-moderation-index.png');
    if (config.letterIds?.demo?.[0]) {
      await pub('admin', 'Moderation detail', `/admin/moderation/letters/${config.letterIds.demo[0]}`, 'admin/16-moderation-show.png');
    }
    await pub('admin', 'Audit log', '/admin/audit', 'admin/17-audit.png');
    await pub('admin', 'Health check', '/admin/health', 'admin/18-health.png');
    await pub('admin', 'Email tools', '/admin/email-tools', 'admin/19-email-tools.png');
    await pub('admin', 'Admin account', '/admin/account', 'admin/20-account.png');

    await context.close();
  }

  await writeFile(manifestPath, JSON.stringify({ capturedAt: new Date().toISOString(), baseUrl, pages: manifest }, null, 2));
  console.log(`\nDone. ${manifest.length} screenshots saved to ${outputDir}`);

  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
