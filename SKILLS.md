# Agent Skills

The skills this project uses with Claude Code (and other coding agents), where each one comes from, and how to restore them.

## Restore after cloning

```bash
./install-skills.sh            # third-party skills from skills-lock.json, plus their setup
./install-skills.sh --boost    # also refresh the Laravel Boost skills (Sail must be running)
./install-skills.sh --no-setup # skip setup steps such as Playwright's Chromium download
```

Restart Claude Code afterwards so it loads the skills.

## Project skills

| Skill | Source | Stored in git? | Used for |
|---|---|---|---|
| `laravel-best-practices` | Laravel Boost | Yes, `.claude/skills/` | Writing and reviewing Laravel code: Eloquent, validation, security, routes |
| `testing-best-practices` | Laravel Boost | Yes, `.claude/skills/` | Designing and reviewing Pest tests |
| `infer-conventions` | Laravel Boost | Yes, `.claude/skills/` | Recording this app's own conventions |
| `playwright-skill` | [`lackeyjb/playwright-skill`](https://github.com/lackeyjb/playwright-skill) via `npx skills` | No, restored by `install-skills.sh` | Browser testing: clicking through pages, JavaScript checks, screenshots, phone layouts |

### Excluded on purpose

Laravel Boost adds these automatically, but `install-skills.sh --boost` removes them again:

| Skill | Why it's excluded |
|---|---|
| `tailwindcss-development` | The UI is Bootstrap/AdminLTE. Boost detects `tailwindcss` in `package.json` (unused) and would push Tailwind classes into Blade views. |
| `deploying-to-cloud` | For Laravel Cloud. This project runs on Docker/Sail. |

## Notes per skill

**Laravel Boost skills**
- Installed by `vendor/bin/sail artisan boost:install` / `boost:update`, configured in `boost.json`, and committed so a clone has them without Sail running.
- `boost:update` writes its guidelines to `AGENTS.md` only. `CLAUDE.md` has a copy of that block (without the Laravel Cloud section), so after `--boost` copy any `AGENTS.md` changes into it and review `git diff`.
- The Boost MCP server (`.mcp.json`) runs through Sail and needs Laravel ≥ 12.41.

**playwright-skill**
- Recorded in `skills-lock.json` and installed to `.agents/skills/playwright-skill` (universal location for several agents), with a link at `.claude/skills/playwright-skill`. Both are gitignored.
- Its setup (`npm run setup`) installs Playwright and Chromium. Fedora isn't officially supported, so Playwright downloads its Ubuntu build, which works here.
- Security review (2026-09-25): `run.js` only runs the scripts it's given, and `lib/helpers.js` launches browsers and probes `localhost` ports. It has no install hooks and makes no outside network calls. skills.sh rates it "Med Risk" (Snyk) because it can run arbitrary JavaScript, which is inherent to browser automation.
- The app is at `http://localhost` (port 80). The skill's server detection only scans common dev ports and won't find it. AdminLTE shows a loading overlay, so wait for `.preloader` to hide before taking screenshots.

## Not tracked here

User-level skills and plugins (Figma, docs/pdf/xlsx, `/code-review`, `/simplify` and so on) come with Claude Code or the user's own setup, not with this repo.

## Adding or removing a skill

- **Add:** `npx skills add <owner/repo> --skill <name> --yes`. This records the skill in `skills-lock.json`, so `install-skills.sh` picks it up automatically. Commit `skills-lock.json`, add a row to the table above, and run `./install-skills.sh` once so it adds the gitignore entry.
- **Remove:** `npx skills remove <name>`, then delete its row here and its line in `.gitignore`.
- **Boost skill:** change `boost.json`, run `./install-skills.sh --boost`, and commit `.claude/skills/`.
