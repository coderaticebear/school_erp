// FUNCTIONALITY TEST: one school's lifecycle through the real UI, every role, with database checks.
// It WRITES data (a QA class, teacher, student, parent, exam, attendance and marks) and can run once per database.
// Snapshot or reseed the demo database afterwards:
//   node .agents/skills/playwright-skill/run.js tests/e2e/functional.cjs
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { BASE, outputDir, tinker, lastSchoolDay, appToday } = require('./support.cjs');

const OUT = outputDir('functional');
// Attendance can only be taken on a school day, so the lifecycle uses the most recent one.
const TODAY = lastSchoolDay();
const IS_SCHOOL_DAY_TODAY = TODAY === appToday();
const Q = {
    year: '2031-2032', yearRenamed: '2032-2033', subject: 'QA Robotics', subjectRenamed: 'QA Robotics Lab', className: 'QA Grade 9', division: 'Q',
    period: 'QA Club', teacherFirst: 'Quinn', teacherLast: 'Tester', teacherEmail: 'qa.tutor@example.test', teacherPass: 'QaTutor-Pass-2026',
    studentFirst: 'Riley', studentLast: "O'Test", studentEmail: 'qa.student@example.test', studentPass: 'QaStudent-Pass-2026',
    parentFirst: 'Morgan', parentLast: "O'Test", parentEmail: 'qa.parent@example.test', parentPass: 'QaParent-Pass-2026',
    exam: 'QA Unit Test', remark: 'Bus was late',
};

const results = [];
const consoleErrors = [];
let current = '';
const record = (ok, detail = '') => {
    const skipped = ok && detail.startsWith('SKIP:');
    results.push({ step: current, ok, skipped, detail });
    console.log(`${skipped ? 'SKIP' : ok ? 'PASS' : 'FAIL'}  ${current}${detail ? '  — ' + detail : ''}`);
};

/** Run PHP in the app to verify what the UI saved. */
const db = tinker;

async function step(name, page, fn) {
    current = name;
    try {
        const detail = await fn();
        record(true, detail || '');
    } catch (error) {
        record(false, error.message.split('\n')[0].slice(0, 220));
        if (page) await page.screenshot({ path: path.join(OUT, name.replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '.png'), fullPage: true }).catch(() => {});
    }
}

function expect(condition, message) { if (!condition) throw new Error(message); }

async function newSession(browser, email, password, { expectFail = false } = {}) {
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
    const page = await context.newPage();
    page.on('console', m => { if (m.type() === 'error') consoleErrors.push(`${current}: ${m.text()}`); });
    page.on('pageerror', e => consoleErrors.push(`${current}: ${e.message}`));
    await page.goto(`${BASE}/login`);
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await Promise.all([page.waitForLoadState('load'), page.click('button[type=submit]')]);
    await page.waitForLoadState('load');
    if (!expectFail && new URL(page.url()).pathname.startsWith('/login')) throw new Error(`login failed for ${email}`);
    return { context, page };
}

async function submit(page, locator) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), locator.click()]);
}

async function confirmDialog(page) {
    await page.waitForSelector('#confirm-dialog.show', { state: 'visible' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#confirm-dialog-ok')]);
}

async function flash(page) {
    return page.evaluate(() => [...document.querySelectorAll('.alert')].map(a => a.innerText.trim()).join(' | '));
}

(async () => {
    const browser = await chromium.launch({ headless: true });

    // ---------- Authentication ----------
    await step('FT-01 wrong password is rejected with a message', null, async () => {
        const { context, page } = await newSession(browser, 'admin@example.com', 'wrong-password', { expectFail: true });
        const text = await page.textContent('body');
        await context.close();
        expect(/credentials do not match/i.test(text), 'no error message shown');
    });

    const { context: adminCtx, page: admin } = await newSession(browser, 'admin@example.com', 'password');

    await step('FT-01 admin signs in and lands on the dashboard', admin, async () => {
        expect(new URL(admin.url()).pathname === '/admin/dashboard', `landed on ${admin.url()}`);
        return (await admin.textContent('h1')).trim();
    });

    // ---------- Academic years ----------
    await step('FT-02 add, rename and delete an academic year', admin, async () => {
        await admin.goto(`${BASE}/admin/academic-years`);
        await admin.fill('#year', 'QA 2031');
        await submit(admin, admin.locator('button:has-text("Add Year")'));
        expect((await flash(admin)).includes('YYYY-YYYY'), 'badly formatted year was not refused');
        await admin.fill('#year', Q.year);
        await submit(admin, admin.locator('button:has-text("Add Year")'));
        expect((await admin.textContent('main')).includes(Q.year), 'new year not listed');
        const row = admin.locator('tr', { hasText: Q.year });
        await row.locator('button:has-text("Edit")').click();
        const modal = admin.locator('.modal.show');
        await modal.waitFor();
        await modal.locator('input[name=year]').fill(Q.yearRenamed);
        await submit(admin, modal.locator('button:has-text("Save")'));
        expect((await admin.textContent('main')).includes(Q.yearRenamed), 'rename not shown');
        await admin.locator('tr', { hasText: Q.yearRenamed }).locator('button:has-text("Delete")').click();
        await confirmDialog(admin);
        expect(!(await admin.textContent('main')).includes(Q.yearRenamed), 'year still listed after delete');
        expect(db(`echo App\\Models\\AcademicYear::where("is_active", true)->count();`) === '1', 'active year count changed');
        return await flash(admin);
    });

    // ---------- Subjects ----------
    await step('FT-03 add a subject, reject a duplicate, rename it', admin, async () => {
        await admin.goto(`${BASE}/subjects`);
        await admin.fill('#subject_name', Q.subject);
        await admin.fill('#periods_per_week', '2');
        await submit(admin, admin.locator('button:has-text("Add Subject")'));
        await admin.fill('#subject_name', Q.subject.toUpperCase());
        await submit(admin, admin.locator('button:has-text("Add Subject")'));
        const dupError = await admin.locator('.invalid-feedback, .alert-danger').allInnerTexts();
        expect(dupError.join(' ').length > 0, 'duplicate (different case) was accepted');
        const row = admin.locator('tr', { hasText: Q.subject }).first();
        await row.locator('button:has-text("Edit")').click();
        const modal = admin.locator('.modal.show');
        await modal.waitFor();
        await modal.locator('input[name=subject_name]').fill(Q.subjectRenamed);
        await submit(admin, modal.locator('button:has-text("Save")'));
        expect(db(`echo App\\Models\\Subjects::where("subject_name", "${Q.subjectRenamed}")->value("periods_per_week");`) === '2', 'subject not saved');
        return `duplicate refused: "${dupError.join(' ').trim().slice(0, 60)}"`;
    });

    // ---------- Classes and divisions ----------
    await step('FT-04 add a class and a division', admin, async () => {
        await admin.goto(`${BASE}/admin/classes`);
        await admin.click('button:has-text("Add Class")');
        const modal = admin.locator('#addClass');
        await modal.waitFor({ state: 'visible' });
        await modal.locator('#class_name').fill(Q.className);
        await submit(admin, modal.locator('button:has-text("Add")'));
        await admin.fill(`input[aria-label="New division name for ${Q.className}"]`, Q.division);
        await submit(admin, admin.locator('.card', { hasText: Q.className }).locator('button:has-text("Add Division")'));
        const id = db(`echo App\\Models\\Divisions::whereHas("class", fn ($q) => $q->where("class_name", "${Q.className}"))->where("division_name", "${Q.division}")->value("id");`);
        expect(/^\d+$/.test(id), 'division not created');
        Q.divisionId = id;
        return `division id ${id}`;
    });

    // ---------- Bell schedule ----------
    await step('FT-05 add, edit and delete a bell-schedule period', admin, async () => {
        await admin.goto(`${BASE}/admin/periods`);
        await admin.fill('#label', Q.period);
        await admin.fill('#starts_at', '17:00');
        await admin.fill('#ends_at', '17:30');
        await submit(admin, admin.locator('button:has-text("Add Period")'));
        expect((await admin.textContent('main')).includes(Q.period), 'period not listed');
        const row = admin.locator('tr', { hasText: Q.period });
        await row.locator('button:has-text("Edit")').click();
        const modal = admin.locator('.modal.show');
        await modal.waitFor();
        await modal.locator('input[name=ends_at]').fill('17:45');
        await submit(admin, modal.locator('button:has-text("Save")'));
        expect((await admin.locator('tr', { hasText: Q.period }).innerText()).includes('17:45'), 'edit not shown');
        await admin.locator('tr', { hasText: Q.period }).locator('button:has-text("Delete")').click();
        await confirmDialog(admin);
        expect(!(await admin.textContent('main')).includes(Q.period), 'period still listed');
    });

    // ---------- Teachers ----------
    await step('FT-06 add a teacher who teaches the new subject', admin, async () => {
        await admin.goto(`${BASE}/admin/teachers/create`);
        await admin.fill('#first-name', Q.teacherFirst);
        await admin.fill('#last-name', Q.teacherLast);
        await admin.fill('[name=address_line_1]', '1 Test Way');
        await admin.fill('#city', 'Toronto');
        await admin.fill('#province', 'ON');
        await admin.fill('#country', 'Canada');
        await admin.fill('#postal', 'M5V 1A1');
        await admin.fill('#email', Q.teacherEmail);
        await admin.fill('#password', Q.teacherPass);
        const forId = await admin.locator('label', { hasText: Q.subjectRenamed }).getAttribute('for');
        await admin.locator(`label[for="${forId}"]`).click();
        await submit(admin, admin.locator('button[type=submit]:has-text("Add Teacher")'));
        const id = db(`echo App\\Models\\Login::where("email", "${Q.teacherEmail}")->first()?->teacher?->id;`);
        expect(/^\d+$/.test(id), 'teacher not created');
        Q.teacherId = id;
        return await flash(admin);
    });

    await step('FT-06 the teacher list pop-up shows the teacher\'s details', admin, async () => {
        await admin.goto(`${BASE}/teachers`);
        await admin.locator('button.view-button', { hasText: `${Q.teacherFirst} ${Q.teacherLast}` }).click();
        await admin.waitForSelector('#viewDataModal.show');
        await admin.waitForFunction(e => document.querySelector('#viewDataModal').innerText.includes(e), Q.teacherEmail);
        const text = await admin.locator('#viewDataModal').innerText();
        expect(text.includes(Q.subjectRenamed), 'subject missing from pop-up');
    });

    await step('FT-06 edit the teacher', admin, async () => {
        await admin.goto(`${BASE}/admin/teachers/${Q.teacherId}/edit`);
        await admin.fill('#city', 'Ottawa');
        await submit(admin, admin.locator('button[type=submit]:has-text("Save Changes")'));
        expect(db(`echo App\\Models\\Teachers::find(${Q.teacherId})->city;`) === 'Ottawa', 'city not saved');
    });

    await step('FT-07 assign the teacher to the division as class teacher', admin, async () => {
        await admin.goto(`${BASE}/admin/divisions/${Q.divisionId}/teachers`);
        const name = `${Q.teacherFirst} ${Q.teacherLast}`;
        await admin.check(`input[aria-label="${name} teaches this division"]`);
        await admin.check(`input[aria-label="${name} is the class teacher"]`);
        await submit(admin, admin.locator('main button[type=submit]:has-text("Save")'));
        const flag = db(`echo (int) DB::table("teacher_division")->where("teacher_id", ${Q.teacherId})->where("division_id", ${Q.divisionId})->value("class_teacher");`);
        expect(flag === '1', 'class teacher not saved');
    });

    // ---------- Students ----------
    await step('FT-08 a failed Add Student keeps the input but not the passwords', admin, async () => {
        await admin.goto(`${BASE}/admin/addStudent`);
        await admin.fill('#last-name', Q.studentLast);
        await admin.fill('#password', Q.studentPass);
        await admin.evaluate(() => document.querySelectorAll('[required]').forEach(el => el.removeAttribute('required')));
        await submit(admin, admin.locator('#submit'));
        expect(await admin.inputValue('#last-name') === Q.studentLast, 'input not kept');
        expect(await admin.inputValue('#password') === '', 'password refilled');
        expect(await admin.locator('.is-invalid').count() > 0, 'no field errors shown');
    });

    await step('FT-08 add a student with a new parent (Find Parent flow)', admin, async () => {
        await admin.goto(`${BASE}/admin/addStudent`);
        await admin.fill('#first-name', Q.studentFirst);
        await admin.fill('#last-name', Q.studentLast);
        await admin.fill('#dob', '2012-03-04');
        await admin.selectOption('#gender', 'other');
        await admin.selectOption('#blood-group', 'O+');
        await admin.selectOption('#class-division-id', Q.divisionId);
        await admin.fill('#email', Q.studentEmail);
        await admin.fill('#password', Q.studentPass);
        await admin.fill('#p-email', Q.parentEmail);
        await admin.click('#check_p_email');
        await admin.waitForSelector('#parent_form', { state: 'visible' });
        await admin.fill('#parent-first-name', Q.parentFirst);
        await admin.fill('#parent-last-name', Q.parentLast);
        await admin.fill('#parent-area-code', '416');
        await admin.fill('#parent-phone', '5550101');
        await admin.fill('#parent-password', Q.parentPass);
        await admin.fill('[name=address_line_1]', '2 Test Way');
        await admin.fill('#city', 'Toronto');
        await admin.fill('#province', 'ON');
        await admin.fill('#country', 'Canada');
        await admin.fill('#postal', 'M5V 1A2');
        await submit(admin, admin.locator('#submit'));
        const row = db(`$s = App\\Models\\Login::where("email", "${Q.studentEmail}")->first()?->student; echo json_encode([$s?->id, $s?->parent?->login?->email, App\\Models\\StudentClass::where("student_id", $s?->id)->value("class_division_id")]);`);
        const [id, parentEmail, divisionId] = JSON.parse(row);
        expect(id, 'student not created');
        expect(parentEmail === Q.parentEmail, 'parent not linked');
        expect(String(divisionId) === String(Q.divisionId), 'not enrolled in the division');
        Q.studentId = id;
        return await flash(admin);
    });

    await step('FT-08 the student profile and list show the new student', admin, async () => {
        await admin.goto(`${BASE}/students`);
        await admin.fill('input[type=search][aria-controls=studentList]', Q.studentFirst);
        expect((await admin.locator('#studentList tbody').innerText()).includes(Q.studentLast), 'not found with the list search (apostrophe name)');
        await admin.goto(`${BASE}/admin/view/student/${Q.studentId}`);
        const text = await admin.textContent('main');
        expect(text.includes(Q.parentEmail) && text.includes(`${Q.className} - ${Q.division}`), 'profile incomplete');
    });

    await step('FT-08 edit the student', admin, async () => {
        await admin.goto(`${BASE}/admin/students/${Q.studentId}/edit`);
        await admin.fill('#city', 'Mississauga');
        await submit(admin, admin.locator('main button[type=submit]', { hasText: /Save/ }));
        expect(db(`echo App\\Models\\Students::find(${Q.studentId})->city;`) === 'Mississauga', 'edit not saved');
    });

    // ---------- Deactivation ----------
    await step('FT-09 a deactivated student cannot sign in; reactivating restores access', admin, async () => {
        await admin.goto(`${BASE}/admin/students/${Q.studentId}/edit`);
        await admin.click('button:has-text("Deactivate Student")');
        await confirmDialog(admin);
        const blocked = await newSession(browser, Q.studentEmail, Q.studentPass, { expectFail: true });
        const stillOnLogin = new URL(blocked.page.url()).pathname.startsWith('/login');
        await blocked.context.close();
        expect(stillOnLogin, 'deactivated student could sign in');
        await admin.goto(`${BASE}/admin/students/${Q.studentId}/edit`);
        await submit(admin, admin.locator('button:has-text("Reactivate Student")'));
        const back = await newSession(browser, Q.studentEmail, Q.studentPass);
        await back.context.close();
    });

    // ---------- Timetable ----------
    await step('FT-10 generate and publish the new division\'s timetable', admin, async () => {
        await admin.goto(`${BASE}/admin/timetable?division=${Q.divisionId}`);
        await admin.click('button:has-text("Generate")');
        await admin.locator('.dropdown-menu.show button', { hasText: `Only ${Q.className} - ${Q.division}` }).click();
        await confirmDialog(admin);
        const lessons = db(`echo App\\Models\\TimetableEntry::where("division_id", ${Q.divisionId})->count();`);
        expect(Number(lessons) === 2, `expected 2 lessons (periods_per_week), got ${lessons}`);
        expect((await admin.textContent('main')).includes(Q.subjectRenamed), 'grid does not show the subject');
        await submit(admin, admin.locator('button[type=submit]', { hasText: /Publish/ }));
        const published = db(`echo App\\Models\\AcademicYear::current()->timetable_published_at ? "yes" : "no";`);
        expect(published === 'yes', 'not published');
        return `${lessons} lessons, published`;
    });

    await step('FT-10 timetable CSV export includes the new lessons', admin, async () => {
        const r = await adminCtx.request.get(`${BASE}/admin/timetable/export?division=${Q.divisionId}`);
        const body = await r.text();
        expect(r.status() === 200 && body.includes(Q.subjectRenamed), 'export missing subject');
    });

    // ---------- Exams ----------
    await step('FT-11 create an exam', admin, async () => {
        await admin.goto(`${BASE}/admin/exams`);
        const form = admin.locator('form[action$="/admin/exams"]');
        await form.locator('[name=name]').fill(Q.exam);
        await form.locator('[name=starts_on]').fill(TODAY);
        await form.locator('[name=max_marks]').fill('50');
        await form.locator('[name=pass_marks]').fill('20');
        await submit(admin, form.locator('button:has-text("Add Exam")'));
        const id = db(`echo App\\Models\\Exam::where("name", "${Q.exam}")->value("id");`);
        expect(/^\d+$/.test(id), 'exam not created');
        Q.examId = id;
    });

    // ---------- Teacher portal ----------
    const { context: tCtx, page: teacher } = await newSession(browser, Q.teacherEmail, Q.teacherPass);

    await step('FT-12 the new teacher sees their class and timetable', teacher, async () => {
        await teacher.goto(`${BASE}/teacher/classes`);
        expect((await teacher.textContent('main')).includes(`${Q.className} - ${Q.division}`), 'class missing');
        await teacher.goto(`${BASE}/teacher/classes/${Q.divisionId}`);
        expect((await teacher.textContent('main')).includes(Q.studentLast), 'student missing from roster');
        await teacher.goto(`${BASE}/teacher/timetable`);
        expect((await teacher.textContent('main')).includes(Q.subjectRenamed), 'lessons missing from timetable');
    });

    await step('FT-13 the teacher marks attendance (Late with a remark)', teacher, async () => {
        await teacher.goto(`${BASE}/teacher/attendance?division=${Q.divisionId}&date=${TODAY}`);
        const row = teacher.locator('.attendance-row', { hasText: Q.studentFirst });
        await row.locator('label', { hasText: 'Late' }).click();
        await row.locator('button.remark-toggle').click();
        await row.locator(`input[name="attendance[${Q.studentId}][remark]"]`).fill(Q.remark);
        await submit(teacher, teacher.locator('form[method=post] button[type=submit]'));
        const saved = db(`echo json_encode(App\\Models\\Attendance::where("student_id", ${Q.studentId})->where("date", "${TODAY}")->first(["status", "remark"]));`);
        const a = JSON.parse(saved);
        expect(a && a.status === 'late' && a.remark === Q.remark, `saved: ${saved}`);
        return await flash(teacher);
    });

    await step('FT-14 the teacher enters marks; out-of-range marks are refused', teacher, async () => {
        await teacher.goto(`${BASE}/marks/${Q.examId}/${Q.divisionId}/${db(`echo App\\Models\\Subjects::where("subject_name", "${Q.subjectRenamed}")->value("id");`)}`);
        const input = teacher.locator(`input[name="marks[${Q.studentId}][value]"]`);
        await input.evaluate(el => el.removeAttribute('max'));
        await input.fill('55');
        await submit(teacher, teacher.locator('main button[type=submit]'));
        expect((await teacher.textContent('main')).includes('cannot be more than 50'), 'over-max marks accepted');
        await teacher.locator(`input[name="marks[${Q.studentId}][value]"]`).fill('42');
        await submit(teacher, teacher.locator('main button[type=submit]'));
        const mark = db(`echo App\\Models\\Mark::where("exam_id", ${Q.examId})->where("student_id", ${Q.studentId})->value("marks");`);
        expect(Number(mark) === 42, `saved mark ${mark}`);
    });

    // ---------- Results ----------
    await step('FT-15 admin sees results and publishes them', admin, async () => {
        await admin.goto(`${BASE}/admin/exams/${Q.examId}/divisions/${Q.divisionId}`);
        const text = await admin.textContent('main');
        expect(text.includes(Q.studentLast) && text.includes('42'), 'results page missing mark');
        await admin.goto(`${BASE}/admin/exams/${Q.examId}`);
        await admin.click('button:has-text("Publish Results")');
        await confirmDialog(admin);
        expect(db(`echo App\\Models\\Exam::find(${Q.examId})->results_published_at ? "yes" : "no";`) === 'yes', 'not published');
    });

    await step('FT-15 marks are locked once results are published', teacher, async () => {
        const subjectId = db(`echo App\\Models\\Subjects::where("subject_name", "${Q.subjectRenamed}")->value("id");`);
        await teacher.goto(`${BASE}/marks/${Q.examId}/${Q.divisionId}/${subjectId}`);
        const disabled = await teacher.locator(`input[name="marks[${Q.studentId}][value]"]`).isDisabled().catch(() => true);
        const text = await teacher.textContent('main');
        expect(disabled || /published|locked/i.test(text), 'sheet still editable');
    });
    await tCtx.close();

    // ---------- Student and parent portals ----------
    await step('FT-16 the student sees timetable, attendance and report card', null, async () => {
        const { context, page } = await newSession(browser, Q.studentEmail, Q.studentPass);
        await page.goto(`${BASE}/student/attendance`);
        const att = await page.textContent('main');
        await page.goto(`${BASE}/student/results/${Q.examId}`);
        const card = await page.textContent('main');
        await page.goto(`${BASE}/student/timetable`);
        const tt = await page.textContent('main');
        await context.close();
        expect(/late/i.test(att), 'attendance missing Late');
        expect(card.includes('42') && card.includes(Q.subjectRenamed), 'report card missing mark');
        expect(tt.includes(Q.subjectRenamed), 'timetable missing lessons');
    });

    await step('FT-16 the parent sees their child, and only their child', null, async () => {
        const { context, page } = await newSession(browser, Q.parentEmail, Q.parentPass);
        await page.goto(`${BASE}/parent/dashboard`);
        const dash = await page.textContent('main');
        await page.goto(`${BASE}/parent/children/${Q.studentId}/results/${Q.examId}`);
        const card = await page.textContent('main');
        const other = await context.request.get(`${BASE}/parent/children/1`, { maxRedirects: 0 });
        await context.close();
        expect(dash.includes(Q.studentFirst), 'child missing on dashboard');
        expect(card.includes('42'), 'report card missing mark');
        expect(other.status() === 403, `another child gave HTTP ${other.status()}`);
    });

    // ---------- Reports ----------
    await step('FT-17 reports and CSV exports include the new data', admin, async () => {
        await admin.goto(`${BASE}/admin/reports/attendance?division=${Q.divisionId}&from=${TODAY}&to=${TODAY}`);
        expect((await admin.textContent('main')).includes(Q.studentLast), 'attendance report missing student');
        const csv = await (await adminCtx.request.get(`${BASE}/admin/reports/attendance?division=${Q.divisionId}&from=${TODAY}&to=${TODAY}&format=csv`)).text();
        expect(csv.includes(Q.studentFirst), 'attendance CSV missing student');
        await admin.goto(`${BASE}/admin/reports/exams?exam=${Q.examId}`);
        expect((await admin.textContent('main')).includes(`${Q.className} - ${Q.division}`), 'exam report missing division');
        const results = await (await adminCtx.request.get(`${BASE}/admin/exams/${Q.examId}/divisions/${Q.divisionId}?format=csv`)).text();
        expect(results.includes('42'), 'results CSV missing mark');
    });

    await step('FT-18 admin dashboard reflects today\'s attendance', admin, async () => {
        if (!IS_SCHOOL_DAY_TODAY) {
            return `SKIP: today is not a school day, so the dashboard's "today" has no attendance (lifecycle used ${TODAY})`;
        }
        await admin.goto(`${BASE}/admin/dashboard`);
        const row = await admin.locator('li', { hasText: `${Q.className} - ${Q.division}` }).first().innerText();
        expect(/Marked/i.test(row), `row: ${row}`);
    });

    // ---------- Sign out ----------
    await step('FT-19 sign out ends the session', admin, async () => {
        await admin.click('.navbar .user-menu > a, .navbar a.dropdown-toggle:has-text("admin@example.com")');
        await Promise.all([admin.waitForNavigation({ waitUntil: 'load' }), admin.locator('a:has-text("Log Out"), a:has-text("Logout"), button:has-text("Log Out")').first().click()]);
        const r = await adminCtx.request.get(`${BASE}/admin/dashboard`, { maxRedirects: 0 });
        expect(r.status() === 302 && (r.headers().location || '').endsWith('/login'), `dashboard after logout: HTTP ${r.status()}`);
    });

    await adminCtx.close();
    await browser.close();

    const failed = results.filter(r => !r.ok);
    const skipped = results.filter(r => r.skipped).length;
    console.log(`\n==== FUNCTIONALITY SUMMARY ====\n${results.length - failed.length - skipped}/${results.length} steps passed${skipped ? `, ${skipped} skipped` : ''} (school day used: ${TODAY})`);
    console.log(`browser console / page errors: ${consoleErrors.length}`);
    consoleErrors.slice(0, 5).forEach(e => console.log('  ' + e));
    fs.writeFileSync(path.join(OUT, 'functional-results.json'), JSON.stringify({ results, consoleErrors }, null, 2));
    process.exitCode = failed.length ? 1 : 0;
})();
