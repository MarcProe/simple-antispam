/**
 * Screenshot capture for the Math CAPTCHA integration test.
 *
 * Drives a real browser (Chromium via Playwright) against a running YOURLS
 * instance and captures screenshots of the CAPTCHA at every stage:
 *
 *   1. login page
 *   2. admin page with the CAPTCHA field on the shortening form
 *   3. close-up of the CAPTCHA field
 *   4. wrong answer rejected (error feedback)
 *   5. correct answer accepted (URL shortened)
 *
 * Because this runs through the browser it also exercises the plugin's
 * JavaScript hook (the `add_link` wrapper that injects the answer into the
 * AJAX request) — something the curl-based tests cannot cover.
 *
 * Requires a YOURLS instance prepared the same way as the integration test
 * (see .github/workflows/integration-test.yml or tests/integration/test-local.sh).
 *
 * Configuration via environment variables:
 *   BASE_URL        (default http://localhost:8080)
 *   ADMIN_USER      (default test-admin)
 *   ADMIN_PASS      (default test-password)
 *   SCREENSHOT_DIR  (default tests/integration/screenshots)
 *   CHROMIUM_PATH   (optional explicit Chromium executable)
 *
 * Usage:  node tests/integration/screenshots.mjs
 */

import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const ADMIN_USER = process.env.ADMIN_USER || 'test-admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'test-password';
const SCREENSHOT_DIR = resolve(process.env.SCREENSHOT_DIR || 'tests/integration/screenshots');

mkdirSync(SCREENSHOT_DIR, { recursive: true });

const shot = (name) => resolve(SCREENSHOT_DIR, name);
let failures = 0;

const check = (ok, label) => {
  console.log(`${ok ? 'PASS' : 'FAIL'}: ${label}`);
  if (!ok) failures++;
};

const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || undefined,
});

try {
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  // --- 1. Login page ---
  await page.goto(`${BASE_URL}/admin/`);
  await page.waitForSelector('#username');
  await page.screenshot({ path: shot('01-login-page.png'), fullPage: true });
  console.log('Captured 01-login-page.png');

  // --- 2. Admin page with the CAPTCHA field ---
  await page.fill('#username', ADMIN_USER);
  await page.fill('#password', ADMIN_PASS);
  await Promise.all([page.waitForLoadState('networkidle'), page.keyboard.press('Enter')]);

  await page.waitForSelector('#math-captcha-field');
  await page.screenshot({ path: shot('02-admin-page-with-captcha.png'), fullPage: true });
  console.log('Captured 02-admin-page-with-captcha.png');

  const readQuestion = async () => {
    const text = await page.textContent('#math-captcha-question');
    const m = text.match(/(\d+)\s*\+\s*(\d+)/);
    if (!m) throw new Error(`Unexpected CAPTCHA question: "${text}"`);
    return { question: `${m[1]} + ${m[2]}`, answer: Number(m[1]) + Number(m[2]) };
  };

  let { question } = await readQuestion();
  check(true, `CAPTCHA field is on the form (question: ${question})`);

  // --- 3. Close-up of the CAPTCHA field ---
  await page.locator('#math-captcha-field').screenshot({ path: shot('03-captcha-field-closeup.png') });
  console.log('Captured 03-captcha-field-closeup.png');

  // --- 4. Wrong answer rejected ---
  // Operands are 1-99, so 99999 can never be the right answer.
  await page.fill('#add-url', 'https://example.com/wrong-answer');
  await page.fill('#math-captcha-answer', '99999');
  await page.click('#add-button');
  // YOURLS renders feedback() messages in a jquery.notifyBar element that
  // slides down over 400ms. Playwright reports it visible from the first
  // animation frame (height ~1px), so wait until it is fully expanded
  // before taking a screenshot.
  const waitForFeedbackBar = async () => {
    await page.locator('#__notifyBar').first().waitFor({ timeout: 10000 });
    await page.waitForFunction(
      () => (document.querySelector('#__notifyBar')?.getBoundingClientRect().height ?? 0) > 30,
      undefined,
      { timeout: 10000 },
    );
    await page.waitForTimeout(300);
    return page.locator('#__notifyBar').first().textContent();
  };

  const wrongText = await waitForFeedbackBar();
  check(/incorrect/i.test(wrongText), `wrong answer rejected ("${wrongText.trim()}")`);
  await page.screenshot({ path: shot('04-wrong-answer-rejected.png'), fullPage: true });
  console.log('Captured 04-wrong-answer-rejected.png');

  // --- 5. Correct answer accepted ---
  // A wrong attempt consumes the question and generates a new one, so reload
  // to display the question that matches the current session answer.
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForSelector('#math-captcha-field');
  const fresh = await readQuestion();

  // YOURLS_UNIQUE_URLS rejects duplicates, so use a URL no other test adds.
  const uniqueUrl = `https://example.com/browser-test-${Date.now()}`;
  await page.fill('#add-url', uniqueUrl);
  await page.fill('#math-captcha-answer', String(fresh.answer));
  await page.click('#add-button');
  const newRow = page.locator('#main_table tbody tr', { hasText: uniqueUrl });
  await newRow.first().waitFor({ timeout: 10000 });
  await waitForFeedbackBar();
  check(true, `correct answer accepted, URL shortened (${fresh.question} = ${fresh.answer})`);
  await page.screenshot({ path: shot('05-correct-answer-shortened.png'), fullPage: true });
  console.log('Captured 05-correct-answer-shortened.png');

  // --- 6. Missing answer rejected client-side ---
  // The plugin's JavaScript hook blocks submission when the answer field is
  // empty, before any request is sent — a separate path from the server-side
  // check the curl tests exercise. Reload first for a clean form.
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForSelector('#math-captcha-field');
  await page.fill('#add-url', 'https://example.com/missing-answer');
  await page.fill('#math-captcha-answer', '');
  await page.click('#add-button');
  const missingText = await waitForFeedbackBar();
  check(/captcha/i.test(missingText), `missing answer rejected client-side ("${missingText.trim()}")`);
  await page.screenshot({ path: shot('06-missing-answer-rejected.png'), fullPage: true });
  console.log('Captured 06-missing-answer-rejected.png');

  // --- 7. Mobile viewport ---
  // Same logged-in session, narrow screen: verify the CAPTCHA field renders
  // sensibly on a phone and document it.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForSelector('#math-captcha-field');
  const mobileVisible = await page.locator('#math-captcha-field').isVisible();
  check(mobileVisible, 'CAPTCHA field renders on a mobile viewport (390px)');
  await page.screenshot({ path: shot('07-mobile-form.png'), fullPage: true });
  console.log('Captured 07-mobile-form.png');
} finally {
  await browser.close();
}

console.log(`\nScreenshots saved to ${SCREENSHOT_DIR}`);
if (failures > 0) {
  console.error(`${failures} check(s) failed`);
  process.exit(1);
}
