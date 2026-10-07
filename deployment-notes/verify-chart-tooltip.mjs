import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('C:/Users/HP-MC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/(\w:)/, '$1')), '..');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/build/manifest.json'), 'utf8'));
const css = fs.readFileSync(path.join(root, 'public/build', manifest['resources/css/app.css'].file), 'utf8');
const fixture = fs.readFileSync(path.join(root, 'deployment-notes/chart-hover-fixture.html'), 'utf8');
const browser = await chromium.launch({ headless: true, executablePath: 'C:/Users/HP-MC/AppData/Local/ms-playwright/chromium_headless_shell-1243/chrome-headless-shell-win64/chrome-headless-shell.exe' });
const evidence = [];
try {
  for (const width of [1100, 390, 320]) {
    const page = await browser.newPage({ viewport: { width, height: 600 } });
    await page.setContent(`<style>${css}</style><body style="margin:16px;background:#f7f6f9"><section class="dashboard-panel" style="max-width:860px;margin:auto"><div class="dashboard-panel__body">${fixture}</div></section></body>`);
    const visible = async () => page.locator('.weekly-chart__tooltip').evaluateAll(nodes => nodes.filter(n => {
      const s = getComputedStyle(n); return s.visibility === 'visible' && Number(s.opacity) > 0;
    }).map(n => ({ id:n.id, text:n.textContent, rect:{left:n.getBoundingClientRect().left,right:n.getBoundingClientRect().right} })));
    const check = async (id, label) => {
      assert((await visible()).length <= 1, `${width}: ${label} overlaps during transition`);
      await page.waitForTimeout(180);
      const tips = await visible();
      assert.equal(tips.length, 1, `${width}: ${label} tooltip count`);
      assert.equal(tips[0].id, id, `${width}: ${label} selected tooltip`);
      const bounds = await page.locator('.dashboard-panel').boundingBox();
      assert(tips[0].rect.left >= bounds.x && tips[0].rect.right <= bounds.x + bounds.width, `${width}: ${label} clipped`);
      evidence.push({ width, label, tooltip:id, insidePanel:true, visibleCount:1 });
    };
    const green = page.locator('.weekly-chart__day').nth(0).locator('.weekly-chart__segment--overtime');
    const orange = page.locator('.weekly-chart__day').nth(0).locator('.weekly-chart__segment--regular');
    await green.hover(); await check('weekly-chart-tooltip-0-overtime', 'Monday green');
    await orange.hover(); await check('weekly-chart-tooltip-0-regular', 'Monday orange');
    await green.focus();
    await page.locator('.weekly-chart__day').nth(1).locator('.weekly-chart__segment--regular').hover();
    await check('weekly-chart-tooltip-1-regular', 'hover next day while green focused');
    const tuesday = page.locator('.weekly-chart__day').nth(1);
    const thin = await tuesday.locator('.weekly-chart__segment--overtime').boundingBox();
    const regular = await tuesday.locator('.weekly-chart__segment--regular').boundingBox();
    const track = await tuesday.locator('.weekly-chart__track').boundingBox();
    assert(thin.height > 0 && thin.height < 15, 'Fixture must contain a thin 30-minute strip');
    assert(Math.abs(thin.y + thin.height - regular.y) < 0.1, 'Colour boundary must match hit areas');
    for (const [label, y] of [['thin green top', thin.y + 1], ['thin green bottom', thin.y + thin.height - 1]]) {
      await page.mouse.move(thin.x + thin.width / 2, y);
      await check('weekly-chart-tooltip-1-overtime', label);
    }
    await page.mouse.move(regular.x + regular.width / 2, regular.y + 1);
    await check('weekly-chart-tooltip-1-regular', 'orange immediately below thin green');
    await page.mouse.move(track.x + track.width / 2, track.y + 2);
    await page.waitForTimeout(180);
    assert.equal((await visible()).length, 0, 'Unfilled space must not trigger a populated bar tooltip');
    evidence.push({width, label:'unfilled space', visibleCount:0});
    await page.locator('.weekly-chart__day').nth(6).locator('.weekly-chart__track').hover(); await check('weekly-chart-tooltip-6', 'Sunday empty edge');
    await page.mouse.move(0, 0);
    await green.focus(); await check('weekly-chart-tooltip-0-overtime', 'keyboard green');
    await orange.focus(); await check('weekly-chart-tooltip-0-regular', 'keyboard orange');
    await green.hover();
    await page.waitForTimeout(180);
    await page.screenshot({ path:path.join(root, `deployment-notes/chart-hover-${width}.png`) });
    await page.close();
  }
  fs.writeFileSync(path.join(root, 'deployment-notes/chart-hover-browser-evidence.json'), JSON.stringify(evidence, null, 2));
  console.log(JSON.stringify({ passed:evidence.length, viewports:[1100,390,320], singleTooltip:true, alignment:true }));
} finally { await browser.close(); }
