// Browser checks for the School ERP JavaScript interactions and every role's pages.
// It changes demo data (generates timetables, adds and deletes a subject); reseed afterwards if that matters:
//   node .agents/skills/playwright-skill/run.js tests/e2e/browser-suite.cjs
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require('playwright');
const { BASE, outputDir, lastSchoolDay } = require('./support.cjs');

const SCHOOL_DAY = lastSchoolDay();

const ART = outputDir('browser-suite');

const results = [];
const consoleProblems = [];
const record = (name, ok, detail = '') => {
    results.push({ name, ok, detail });
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`);
};
async function check(name, fn) {
    try {
        const detail = await fn();
        record(name, true, detail || '');
    } catch (error) {
        record(name, false, (error.message || String(error)).split('\n')[0]);
    }
}
const expectTrue = (cond, msg) => { if (!cond) throw new Error(msg); };

async function newSession(browser, email, viewport) {
    const context = await browser.newContext({ viewport: viewport || { width: 1440, height: 900 } });
    const page = await context.newPage();
    page.on('console', m => { if (m.type() === 'error') consoleProblems.push(`[${email}] console: ${m.text()} @ ${page.url()}`); });
    page.on('pageerror', e => consoleProblems.push(`[${email}] pageerror: ${e.message} @ ${page.url()}`));
    page.on('dialog', d => d.accept());
    await page.goto(`${BASE}/login`);
    await page.locator('input[name=email]').fill(email);
    await page.locator('input[name=password]').fill('password');
    await Promise.all([page.waitForURL(u => !u.pathname.startsWith('/login')), page.locator('button[type=submit]').click()]);
    return { context, page };
}

const alertText = page => page.locator('.alert').first().innerText().catch(() => '');

(async () => {
    const browser = await chromium.launch({ headless: process.env.PW_HEADLESS === 'true' });

    // ---------------- ADMIN ----------------
    const { context: adminCtx, page } = await newSession(browser, 'admin@example.com');

    await check('Timetable: clicking a slot opens the edit dialog', async () => {
        await page.goto(`${BASE}/admin/timetable?division=1`);
        await page.locator('.slot-editable').first().click();
        await page.locator('#slotModal').waitFor({ state: 'visible' });
        const title = await page.locator('#slotTitle').innerText();
        expectTrue(/Monday/.test(title), `title was "${title}"`);
        return `title "${title}"`;
    });

    await check('Timetable: changing subject refreshes the teacher list', async () => {
        const subjects = page.locator('#slotSubject option');
        const count = await subjects.count();
        expectTrue(count > 1, `only ${count} subject(s)`);
        const values = await subjects.evaluateAll(o => o.map(x => x.value));
        const teachersFor = async v => {
            await page.locator('#slotSubject').selectOption(v);
            return page.locator('#slotTeacher option').evaluateAll(o => o.map(x => x.textContent.trim()));
        };
        const a = await teachersFor(values[0]);
        const b = await teachersFor(values[1]);
        expectTrue(a.length > 0 && b.length > 0, 'a subject had no teachers listed');
        return `${values.length} subjects; e.g. [${a.join(', ')}] vs [${b.join(', ')}]`;
    });

    const closeSlotModal = async () => {
        await page.locator('#slotModal').getByRole('button', { name: 'Cancel' }).click();
        await page.locator('#slotModal').waitFor({ state: 'hidden' });
    };

    await check('Timetable: busy teachers are shown disabled somewhere in the week', async () => {
        await closeSlotModal();
        const slots = page.locator('.slot-editable');
        const n = Math.min(await slots.count(), 12);
        for (let i = 0; i < n; i++) {
            await slots.nth(i).click();
            await page.locator('#slotModal').waitFor({ state: 'visible' });
            for (const v of await page.locator('#slotSubject option').evaluateAll(o => o.map(x => x.value))) {
                await page.locator('#slotSubject').selectOption(v);
                const busy = await page.locator('#slotTeacher option:disabled').count();
                if (busy > 0) {
                    const text = await page.locator('#slotTeacher option:disabled').first().innerText();
                    await closeSlotModal();
                    return `slot ${i}: "${text}"`;
                }
            }
            await closeSlotModal();
        }
        throw new Error(`no disabled (busy) teacher found in first ${n} slots`);
    });

    await check('Timetable: saving a slot works', async () => {
        await page.locator('.slot-editable').first().click();
        await page.locator('#slotModal').waitFor({ state: 'visible' });
        const enabled = page.locator('#slotTeacher option:not(:disabled)');
        expectTrue(await enabled.count() > 0, 'no free teacher for first subject');
        await Promise.all([page.waitForLoadState('load'), page.getByRole('button', { name: 'Save', exact: true }).click()]);
        await page.waitForURL(/division=1/);
        const text = await alertText(page);
        expectTrue(/Lesson saved/.test(text), `alert: "${text.trim()}"`);
        return text.trim().replace(/\s+/g, ' ');
    });

    await check('Timetable: clearing a slot works and shows Free', async () => {
        await page.locator('.slot-editable').first().click();
        await page.locator('#slotModal').waitFor({ state: 'visible' });
        await Promise.all([page.waitForLoadState('load'), page.locator('#clearButton').click()]);
        const text = await alertText(page);
        expectTrue(/Lesson cleared/.test(text), `alert: "${text.trim()}"`);
        const first = await page.locator('.slot-editable').first().innerText();
        expectTrue(/Free/.test(first), `first slot shows "${first}"`);
    });

    await check('Timetable: teacher filter switches to a teacher view', async () => {
        await Promise.all([page.waitForURL(/teacher=/), page.locator('#teacher-select').selectOption({ index: 1 })]);
        expectTrue(!(await page.locator('.slot-editable').count()), 'teacher view should not be editable');
        return new URL(page.url()).search;
    });

    await check('Timetable: division filter switches back', async () => {
        await page.goto(`${BASE}/admin/timetable?teacher=2`);
        await Promise.all([page.waitForURL(/division=2/), page.locator('#division-select').selectOption({ index: 1 })]);
        expectTrue(!page.url().includes('teacher=2'), `url ${page.url()}`);
        return new URL(page.url()).search;
    });

    await check('Timetable: Generate menu regenerates one division', async () => {
        await page.goto(`${BASE}/admin/timetable?division=1`);
        await page.getByRole('button', { name: /Generate/ }).click();
        await page.getByRole('button', { name: /^Only / }).click();
        await page.locator('#confirm-dialog').waitFor({ state: 'visible' });
        await Promise.all([page.waitForURL(/division=/), page.locator('#confirm-dialog-ok').click()]);
        const text = await alertText(page);
        expectTrue(/Generated \d+ lessons/.test(text), `alert: "${text.trim()}"`);
        return text.trim().replace(/\s+/g, ' ').replace(/^×\s*/, '');
    });

    await check('Timetable: print layout hides sidebar and controls', async () => {
        await page.emulateMedia({ media: 'print' });
        const hidden = await page.evaluate(() => ['.main-sidebar', '.main-header', '#timetable-filters']
            .map(s => document.querySelector(s)).map(el => !el || getComputedStyle(el).display === 'none'));
        await page.screenshot({ path: path.join(ART, 'timetable-print.png'), fullPage: true });
        await page.emulateMedia({ media: 'screen' });
        expectTrue(hidden.every(Boolean), `visible in print: ${JSON.stringify(hidden)}`);
        await page.screenshot({ path: path.join(ART, 'timetable.png'), fullPage: true });
        return 'screenshots: timetable.png, timetable-print.png';
    });

    await check('Teachers: table search filters rows', async () => {
        await page.goto(`${BASE}/teachers`);
        const search = page.locator('#teacherList_filter input');
        await search.waitFor();
        const before = await page.locator('#teacherList tbody tr').count();
        await search.fill('Demo');
        const after = await page.locator('#teacherList tbody tr').count();
        expectTrue(after >= 1 && after < before, `rows before ${before}, after ${after}`);
        await search.fill('');
        return `${before} → ${after} rows`;
    });

    await check('Teachers: View opens the detail pop-up', async () => {
        await page.locator('.view-button').first().click();
        await page.locator('#viewDataModal').waitFor({ state: 'visible' });
        const body = await page.locator('#viewDataModal .modal-body').innerText();
        expectTrue(/Email/.test(body) && /Subjects/.test(body), `body: ${body}`);
        await page.keyboard.press('Escape');
        return body.replace(/\s+/g, ' ').slice(0, 90);
    });

    await check('Add Student: one page, every section visible', async () => {
        await page.goto(`${BASE}/admin/addStudent`);
        for (const title of ['Student', 'Student Login', 'Parent / Guardian', 'Address']) {
            await page.getByRole('heading', { name: title, exact: true }).waitFor({ state: 'visible', timeout: 3000 });
        }
        expectTrue(await page.locator('#submit').isVisible(), 'Add Student button hidden');
    });

    await check('Add Student: parent lookup finds an existing parent by name', async () => {
        await page.locator('#p-email').fill('PARENT@example.com');
        await page.locator('#check_p_email').click();
        await page.locator('#parent-lookup-status').getByText(/Found: /).waitFor({ timeout: 5000 });
        expectTrue(!(await page.locator('#parent_form').isVisible()), 'new-parent fields still visible');
        expectTrue((await page.locator('#parent_id').inputValue()) !== '', 'parent_id not set');
        await page.locator('#p-email').fill('changed@example.test');
        expectTrue((await page.locator('#parent_id').inputValue()) === '', 'editing the email should clear the linked parent');
        return (await page.locator('#parent-lookup-status').innerText()).trim();
    });

    await check('Add Student: unknown email reveals the new-parent fields', async () => {
        await page.locator('#p-email').fill('nobody-here@example.test');
        await page.locator('#check_p_email').click();
        await page.locator('#parent_form').waitFor({ state: 'visible', timeout: 5000 });
        expectTrue((await page.locator('#parent_id').inputValue()) === '', 'parent_id still set');
    });

    await check('Add Student: errors appear next to fields and typed values are kept', async () => {
        await page.goto(`${BASE}/admin/addStudent`);
        await page.locator('#first-name').fill('Browser');
        await page.locator('#submit').click();
        await page.locator('.is-invalid').first().waitFor({ timeout: 5000 });
        await page.waitForFunction(() => document.activeElement?.classList.contains('is-invalid'), null, { timeout: 3000 }).catch(() => {});
        const invalid = await page.locator('.is-invalid').count();
        const messages = await page.locator('.invalid-feedback').count();
        const kept = await page.locator('#first-name').inputValue();
        const focused = await page.evaluate(() => document.activeElement?.classList.contains('is-invalid'));
        expectTrue(invalid > 0 && messages > 0, `invalid ${invalid}, messages ${messages}`);
        expectTrue(kept === 'Browser', `first name was "${kept}"`);
        expectTrue(focused, 'first invalid field not focused');
        return `${invalid} fields marked, first one focused, first name kept`;
    });

    await check('Timetable: regenerating all divisions places every lesson', async () => {
        await page.goto(`${BASE}/admin/timetable`);
        await page.getByRole('button', { name: /Generate/ }).click();
        await page.getByRole('button', { name: 'All Divisions' }).click();
        await page.locator('#confirm-dialog').waitFor({ state: 'visible' });
        await Promise.all([page.waitForLoadState('load'), page.locator('#confirm-dialog-ok').click()]);
        await page.locator('.alert').first().waitFor();
        const text = (await alertText(page)).trim().replace(/\s+/g, ' ').replace(/^×\s*/, '');
        expectTrue(/Generated \d+ lessons for all divisions\.$/.test(text), `alert: "${text}"`);
        return text;
    });
    await check('Confirm dialog: Cancel keeps the record, Delete removes it', async () => {
        await page.goto(`${BASE}/subjects`);
        await page.getByLabel('Subject Name').fill("Children's Browser Test");
        await Promise.all([page.waitForLoadState('load'), page.getByRole('button', { name: 'Add Subject' }).click()]);
        const row = page.locator('tr', { hasText: "Children's Browser Test" });
        await row.getByRole('button', { name: 'Delete' }).click();
        await page.locator('#confirm-dialog').waitFor({ state: 'visible' });
        const title = await page.locator('#confirm-dialog-title').innerText();
        await page.waitForFunction(() => document.activeElement?.id === 'confirm-dialog-cancel', null, { timeout: 2000 }).catch(() => {});
        const focused = await page.evaluate(() => document.activeElement?.id);
        await page.locator('#confirm-dialog-cancel').click();
        await page.locator('#confirm-dialog').waitFor({ state: 'hidden' });
        expectTrue(await row.count() === 1, 'record deleted after Cancel');
        await row.getByRole('button', { name: 'Delete' }).click();
        await page.locator('#confirm-dialog').waitFor({ state: 'visible' });
        await Promise.all([page.waitForLoadState('load'), page.locator('#confirm-dialog-ok').click()]);
        await page.locator('.alert-success').waitFor();
        expectTrue(await page.locator('tr', { hasText: "Children's Browser Test" }).count() === 0, 'record not deleted after confirming');
        expectTrue(focused === 'confirm-dialog-cancel', `focus started on ${focused}`);
        return `"${title}", focus on Cancel`;
    });
    await adminCtx.close();

    // ---------------- TEACHER ----------------
    const { context: teacherCtx, page: tp } = await newSession(browser, 'teacher@example.com');

    await check('Attendance: status buttons select a status', async () => {
        await tp.goto(`${BASE}/teacher/attendance?division=1&date=${SCHOOL_DAY}`);
        const row = tp.locator('.attendance-row').first();
        await row.locator('label', { hasText: 'Absent' }).click();
        expectTrue(await row.locator('input[value=absent]').isChecked(), 'Absent not checked');
        expectTrue(await row.locator('label', { hasText: 'Absent' }).evaluate(el => el.classList.contains('active')), 'Absent button not highlighted');
    });

    await check('Attendance: "Mark all present" resets every row', async () => {
        await tp.locator('#allPresent').click();
        const states = await tp.locator('input[type=radio][value=present]').evaluateAll(r => r.map(x => x.checked));
        expectTrue(states.length > 0 && states.every(Boolean), `present states ${JSON.stringify(states)}`);
        return `${states.length} students`;
    });

    await check('Attendance: saving shows a confirmation', async () => {
        await tp.locator('.attendance-row').first().locator('label', { hasText: 'Late' }).click();
        await Promise.all([tp.waitForLoadState('load'), tp.getByRole('button', { name: /Save Attendance/ }).click()]);
        const text = await alertText(tp);
        expectTrue(/Attendance saved/.test(text), `alert: "${text.trim()}"`);
        expectTrue(await tp.locator('.attendance-row').first().locator('input[value=late]').isChecked(), 'saved status not shown after reload');
    });

    await check('Attendance: changing date and division reloads the sheet', async () => {
        const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
        await Promise.all([tp.waitForURL(/date=/), tp.locator('#date').fill(yesterday).then(() => tp.locator('#date').dispatchEvent('change'))]);
        expectTrue(tp.url().includes(`date=${yesterday}`), `url ${tp.url()}`);
        await Promise.all([tp.waitForURL(/division=2/), tp.locator('#division').selectOption({ index: 1 })]);
        return new URL(tp.url()).search;
    });
    await check('Attendance: remark opens on demand and totals update live', async () => {
        await tp.goto(`${BASE}/teacher/attendance?division=1&date=${SCHOOL_DAY}`);
        const row = tp.locator('.attendance-row').first();
        await row.locator('.remark-toggle').click();
        expectTrue(await row.locator('input[type=text]').isVisible(), 'remark input not shown');
        await row.locator('label', { hasText: 'Absent' }).click();
        const summary = await tp.locator('#attendance-summary').innerText();
        expectTrue(/1 absent/.test(summary), `summary "${summary}"`);
        return summary;
    });
    await teacherCtx.close();

    await check('Phone: attendance buttons are full-width 44px targets with a sticky save bar', async () => {
        const { context, page: pp } = await newSession(browser, 'teacher@example.com', { width: 390, height: 844 });
        await pp.goto(`${BASE}/teacher/attendance?division=1&date=${SCHOOL_DAY}`);
        const sizes = await pp.locator('.attendance-row').first().locator('.attendance-status > .btn').evaluateAll(els => els.map(e => Math.round(e.getBoundingClientRect().height)));
        const bar = await pp.locator('.attendance-actions').evaluate(e => getComputedStyle(e).position);
        await context.close();
        expectTrue(sizes.length === 4 && sizes.every(h => h >= 44), `button heights ${sizes}`);
        expectTrue(bar === 'sticky', `save bar position ${bar}`);
        return `heights ${sizes.join('/')}, save bar sticky`;
    });

    await check('Phone: student timetable day picker switches days', async () => {
        const { context, page: sp } = await newSession(browser, 'student@example.com', { width: 390, height: 844 });
        await sp.goto(`${BASE}/student/timetable`);
        const visiblePanels = async () => sp.locator('.timetable-day-panel:visible').count();
        expectTrue(await visiblePanels() === 1, 'expected exactly one day visible');
        expectTrue(!(await sp.locator('.timetable-grid').isVisible()), 'week grid should be hidden on phones');
        const tabs = sp.locator('.timetable-day-tab');
        await tabs.nth(1).click();
        const heading = await sp.locator('.timetable-day-panel:visible h3').innerText();
        expectTrue(await tabs.nth(1).getAttribute('aria-selected') === 'true', 'tab not selected');
        await context.close();
        return `switched to "${heading}"`;
    });

    // ---------------- ALL ROLES: every sidebar page, desktop + phone ----------------
    for (const email of ['admin@example.com', 'teacher@example.com', 'student@example.com', 'parent@example.com']) {
        for (const [label, viewport] of [['desktop', { width: 1440, height: 900 }], ['phone', { width: 375, height: 812 }]]) {
            await check(`${email.split('@')[0]} (${label}): every sidebar page loads without overflow`, async () => {
                const { context, page: p } = await newSession(browser, email, viewport);
                const links = await p.locator('.main-sidebar a.nav-link').evaluateAll(as => as.map(a => a.href).filter(h => h.startsWith('http')));
                const problems = [];
                for (const href of [...new Set(links)]) {
                    const response = await p.goto(href);
                    if (!response || response.status() !== 200) problems.push(`${response?.status()} ${href}`);
                    if (label === 'phone') {
                        const overflow = await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
                        if (overflow > 1) problems.push(`horizontal overflow ${overflow}px on ${new URL(href).pathname}`);
                    }
                }
                await p.screenshot({ path: path.join(ART, `${email.split('@')[0]}-${label}.png`), fullPage: true });
                await context.close();
                expectTrue(problems.length === 0, problems.join('; '));
                return `${new Set(links).size} pages`;
            });
        }
    }

    await browser.close();

    console.log('\n==== SUMMARY ====');
    console.log(`${results.filter(r => r.ok).length}/${results.length} checks passed`);
    console.log(`console/page errors: ${consoleProblems.length}`);
    [...new Set(consoleProblems)].slice(0, 20).forEach(p => console.log('  ' + p));
    console.log(`artifacts: ${ART}`);
    process.exitCode = results.every(r => r.ok) && consoleProblems.length === 0 ? 0 : 1;
})();
