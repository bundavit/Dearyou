import { chromium } from 'playwright';
import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const outputDir = path.join(root, 'screenshots');

const viewport = { width: 1280, height: 800 };

const shots = [
  {
    file: 'homepage.png',
    url: '/',
    waitMs: 600,
    session: 'guest',
  },
  {
    file: 'public-letter.png',
    url: (config) => `/l/${config.publishedToken}`,
    waitMs: 1400,
    session: 'guest',
  },
  {
    file: 'letters.png',
    url: '/letters',
    waitMs: 400,
    session: 'creator',
  },
  {
    file: 'create-letter.png',
    url: '/letters/create',
    waitMs: 400,
    session: 'creator',
  },
];

async function loadConfig() {
  const raw = await readFile(path.join(root, 'scripts', 'screenshot-config.json'), 'utf8');
  return JSON.parse(raw);
}

async function login(page, baseUrl, email, password) {
  await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.locator('form button.btn-dearyou').click();
  await page.waitForLoadState('networkidle');
}

async function capture(page, baseUrl, shot, config) {
  const url = typeof shot.url === 'function' ? shot.url(config) : shot.url;
  await page.goto(`${baseUrl}${url}`, { waitUntil: 'networkidle' });
  if (shot.waitMs) {
    await page.waitForTimeout(shot.waitMs);
  }
  await page.screenshot({
    path: path.join(outputDir, shot.file),
    fullPage: false,
  });
  console.log(`Saved screenshots/${shot.file}`);
}

async function main() {
  const config = await loadConfig();
  const { baseUrl } = config;

  await mkdir(outputDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });

  const guestShots = shots.filter((shot) => shot.session === 'guest');
  if (guestShots.length) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    for (const shot of guestShots) {
      await capture(page, baseUrl, shot, config);
    }
    await context.close();
  }

  const creatorShots = shots.filter((shot) => shot.session === 'creator');
  if (creatorShots.length) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    await login(page, baseUrl, config.demo.email, config.demo.password);
    for (const shot of creatorShots) {
      await capture(page, baseUrl, shot, config);
    }
    await context.close();
  }

  await browser.close();
  console.log('\nPortfolio screenshots ready in screenshots/');
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
