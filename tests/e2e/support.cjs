// Shared settings for the browser scripts in tests/e2e (run through the playwright-skill runner).
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const ROOT = path.resolve(__dirname, '..', '..');
const BASE = process.env.TARGET_URL || 'http://localhost';

/** A git-ignored folder under storage/app/e2e for screenshots and result files. */
function outputDir(name) {
    const dir = process.env.PW_ARTIFACT_DIR || path.join(ROOT, 'storage', 'app', 'e2e', name);
    fs.mkdirSync(dir, { recursive: true });
    return dir;
}

/** Run PHP in the app (through Sail) and return its trimmed output. Used to look up and verify records. */
function tinker(php) {
    return execFileSync(path.join(ROOT, 'vendor', 'bin', 'sail'), ['artisan', 'tinker', '--execute', php], { cwd: ROOT, encoding: 'utf8' }).trim();
}

/** The most recent school day (today or earlier) in the app's timezone; attendance can only be taken on school days. */
function lastSchoolDay() {
    return tinker('$d = today(); while (! in_array($d->isoWeekday(), config("school.days"), true)) { $d->subDay(); } echo $d->toDateString();');
}

/** Today's date in the app's timezone. */
function appToday() {
    return tinker('echo today()->toDateString();');
}

module.exports = { ROOT, BASE, outputDir, tinker, lastSchoolDay, appToday };
