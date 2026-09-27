// SMOKE TEST: every page loads for its role, guests and other roles are kept out, exports download.
// Read-only: it signs in as the demo accounts and never changes data. Run it against the seeded demo database:
//   node .agents/skills/playwright-skill/run.js tests/e2e/smoke.cjs
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { BASE, outputDir, tinker } = require('./support.cjs');

const OUT = outputDir('smoke');
const ids = JSON.parse(tinker(`
    $teacher = App\\Models\\Login::where("email", "teacher@example.com")->first()->teacher;
    $parent = App\\Models\\Login::where("email", "parent@example.com")->first()->parent;
    echo json_encode([
        "studentId" => App\\Models\\Login::where("email", "student@example.com")->first()->student->id,
        "childId" => App\\Models\\Students::where("parent_id", $parent->id)->value("id"),
        "otherStudentId" => App\\Models\\Students::where("parent_id", "<>", $parent->id)->value("id"),
        "teacherId" => App\\Models\\Teachers::value("id"),
        "examId" => App\\Models\\Exam::value("id"),
        "pubExamId" => App\\Models\\Exam::whereNotNull("results_published_at")->value("id"),
        "divisionId" => App\\Models\\Divisions::value("id"),
        "tDivId" => $teacher->divisions()->value("divisions.id"),
        "tSubId" => $teacher->subjects()->value("subjects.id"),
        "otherDivId" => App\\Models\\Divisions::whereNotIn("id", $teacher->divisions()->pluck("divisions.id"))->value("id"),
        "subjectId" => App\\Models\\Subjects::value("id"),
    ]);`));
const results = [];
const record = (area, name, ok, detail = '') => { results.push({ area, name, ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  [${area}] ${name}${detail ? '  — ' + detail : ''}`); };

const PAGES = {
    admin: ['/admin/dashboard', '/students', '/admin/addStudent', `/admin/view/student/${ids.studentId}`, `/admin/students/${ids.studentId}/edit`,
        '/teachers', '/admin/teachers/create', `/admin/teachers/${ids.teacherId}/edit`, '/subjects', '/admin/classes',
        `/admin/divisions/${ids.divisionId}/teachers`, '/admin/academic-years', '/admin/timetable', `/admin/timetable?division=${ids.divisionId}`,
        `/admin/timetable?teacher=${ids.teacherId}`, '/admin/periods', '/admin/exams', `/admin/exams/${ids.examId}`,
        `/admin/exams/${ids.examId}/divisions/${ids.divisionId}`, `/admin/exams/${ids.examId}/students/${ids.studentId}`, '/marks',
        `/marks/${ids.examId}/${ids.divisionId}/${ids.subjectId}`, '/admin/reports/attendance', '/admin/reports/exams'],
    teacher: ['/teacher/dashboard', '/teacher/timetable', '/teacher/classes', `/teacher/classes/${ids.tDivId}`, '/teacher/attendance', '/marks',
        `/marks/${ids.examId}/${ids.tDivId}/${ids.tSubId}`],
    student: ['/student/dashboard', '/student/timetable', '/student/attendance', '/student/results', `/student/results/${ids.pubExamId}`],
    parent: ['/parent/dashboard', `/parent/children/${ids.childId}`, `/parent/children/${ids.childId}/timetable`, `/parent/children/${ids.childId}/attendance`,
        `/parent/children/${ids.childId}/results`, `/parent/children/${ids.childId}/results/${ids.pubExamId}`],
};
const DASHBOARD = { admin: '/admin/dashboard', teacher: '/teacher/dashboard', student: '/student/dashboard', parent: '/parent/dashboard' };

async function signIn(browser, role) {
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
    const page = await context.newPage();
    await page.goto(`${BASE}/login`);
    await page.fill('input[name=email]', `${role}@example.com`);
    await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForURL(u => !u.pathname.startsWith('/login')), page.click('button[type=submit]')]);
    return { context, page };
}

(async () => {
    const browser = await chromium.launch({ headless: true });

    // 1. Public pages and the health check.
    const guest = await browser.newContext();
    for (const url of ['/login', '/password/reset', '/up']) {
        const r = await guest.request.get(BASE + url, { maxRedirects: 0 });
        record('public', `GET ${url}`, r.status() === 200, `HTTP ${r.status()}`);
    }
    const root = await guest.request.get(BASE + '/', { maxRedirects: 0 });
    record('public', 'GET / sends guests to /login', root.status() === 302 && (root.headers().location || '').endsWith('/login'), `HTTP ${root.status()} → ${root.headers().location}`);

    // 2. Guests are sent to /login from every protected page.
    const allPages = [...new Set(Object.values(PAGES).flat()), '/dashboard'];
    let guestOk = 0;
    for (const url of allPages) {
        const r = await guest.request.get(BASE + url, { maxRedirects: 0 });
        if (r.status() === 302 && (r.headers().location || '').endsWith('/login')) guestOk++;
        else record('guest', `GET ${url} should redirect to /login`, false, `HTTP ${r.status()}`);
    }
    record('guest', `every protected page redirects guests to /login`, guestOk === allPages.length, `${guestOk}/${allPages.length}`);
    await guest.close();

    // 3. Every page for every role, in a real browser.
    for (const [role, urls] of Object.entries(PAGES)) {
        const { context, page } = await signIn(browser, role);
        const consoleErrors = [];
        const badResponses = [];
        page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
        page.on('pageerror', e => consoleErrors.push(e.message));
        page.on('response', r => { if (r.status() >= 400 && r.url().startsWith(BASE)) badResponses.push(`${r.status()} ${r.url()}`); });

        const dash = await context.request.get(BASE + '/dashboard', { maxRedirects: 0 });
        record(role, '/dashboard sends the role to its own dashboard', (dash.headers().location || '').endsWith(DASHBOARD[role]), dash.headers().location);

        for (const url of urls) {
            consoleErrors.length = 0; badResponses.length = 0;
            const response = await page.goto(BASE + url, { waitUntil: 'load' });
            const status = response.status();
            const info = await page.evaluate(() => ({
                h1: [...document.querySelectorAll('h1')].map(h => h.textContent.trim()),
                title: document.title,
                errorPage: /Whoops|Server Error|Internal Server Error|Exception|SQLSTATE|Stack trace/i.test(document.body.innerText.slice(0, 4000)),
                main: !!document.querySelector('main#main-content'),
            }));
            const problems = [];
            if (status !== 200) problems.push(`HTTP ${status}`);
            if (info.errorPage) problems.push('error text on page');
            if (info.h1.length !== 1) problems.push(`${info.h1.length} <h1>`);
            if (!info.main) problems.push('no <main>');
            if (consoleErrors.length) problems.push(`console: ${consoleErrors[0].slice(0, 80)}`);
            if (badResponses.length) problems.push(`requests: ${badResponses[0]}`);
            record(role, `GET ${url}`, problems.length === 0, problems.join('; ') || `"${info.h1[0]}"`);
        }

        // 4. The role is kept out of other roles' pages (403, never 500).
        const foreign = Object.entries(PAGES).filter(([other]) => other !== role)
            .flatMap(([, list]) => list).filter(u => !urls.includes(u));
        let denied = 0;
        const leaks = [];
        for (const url of foreign) {
            const r = await context.request.get(BASE + url, { maxRedirects: 0 });
            if (r.status() === 403) denied++; else leaks.push(`${url} → HTTP ${r.status()}`);
        }
        record(role, `other roles' pages are refused with 403`, leaks.length === 0, leaks.length ? leaks.slice(0, 3).join(', ') : `${denied}/${foreign.length}`);

        // 5. Record-level checks inside the role.
        const expectStatus = async (label, url, expected) => {
            const r = await context.request.get(BASE + url, { maxRedirects: 0 });
            record(role, label, r.status() === expected, `${url} → HTTP ${r.status()}`);
        };
        if (role === 'parent') {
            await expectStatus("another family's child is refused", `/parent/children/${ids.otherStudentId}`, 403);
            await expectStatus("another family's report card is refused", `/parent/children/${ids.otherStudentId}/results/${ids.pubExamId}`, 403);
        }
        if (role === 'teacher') {
            await expectStatus("another division's roster is refused", `/teacher/classes/${ids.otherDivId}`, 403);
            await expectStatus("another division's marks sheet is refused", `/marks/${ids.examId}/${ids.otherDivId}/${ids.tSubId}`, 403);
        }
        if (role === 'admin') {
            for (const [label, url] of [
                ['timetable CSV (division)', `/admin/timetable/export?division=${ids.divisionId}`],
                ['timetable CSV (teacher)', `/admin/timetable/export?teacher=${ids.teacherId}`],
                ['exam results CSV', `/admin/exams/${ids.examId}/divisions/${ids.divisionId}?format=csv`],
                ['attendance report CSV', '/admin/reports/attendance?format=csv'],
                ['exam report CSV', '/admin/reports/exams?format=csv'],
            ]) {
                const r = await context.request.get(BASE + url);
                const body = await r.text();
                const ok = r.status() === 200 && /text\/csv/.test(r.headers()['content-type'] || '') && body.split('\n').filter(Boolean).length >= 2;
                record('admin', `export: ${label}`, ok, `HTTP ${r.status()}, ${body.split('\n').filter(Boolean).length} lines`);
            }
            const json = await context.request.get(BASE + `/teachers/${ids.teacherId}`);
            let body = {}; try { body = await json.json(); } catch {}
            const leaksSecret = /password|remember_token|token/i.test(JSON.stringify(body));
            record('admin', 'teacher details JSON (Teachers list pop-up)', json.status() === 200 && /application\/json/.test(json.headers()['content-type'] || '') && body.fname && Array.isArray(body.subjects) && !leaksSecret,
                `HTTP ${json.status()}, keys: ${Object.keys(body).join(',')}`);
            const missing = await context.request.get(BASE + '/admin/view/student/999999', { maxRedirects: 0 });
            record('admin', 'a missing student gives 404, not an error', missing.status() === 404, `HTTP ${missing.status()}`);
        }
        await context.close();
    }

    await browser.close();
    const failed = results.filter(r => !r.ok);
    console.log(`\n==== SMOKE SUMMARY ====\n${results.length - failed.length}/${results.length} checks passed`);
    fs.writeFileSync(path.join(OUT, 'smoke-results.json'), JSON.stringify(results, null, 2));
    process.exitCode = failed.length ? 1 : 0;
})();
