#!/usr/bin/env bash
#
# Reinstall the project's agent skills after a fresh clone.
#
#   ./install-skills.sh            third-party skills from skills-lock.json (+ their setup)
#   ./install-skills.sh --boost    also refresh the Laravel Boost skills (needs Sail running)
#   ./install-skills.sh --no-setup skip per-skill setup steps (e.g. Playwright's Chromium download)
#   ./install-skills.sh --force    reinstall skills that are already installed
#
# The skill list lives in skills-lock.json, which `npx skills add` keeps up to date.
# See SKILLS.md for what each skill is for.

set -euo pipefail

cd "$(dirname "$0")"

RUN_BOOST=false
RUN_SETUP=true
FORCE=false
for arg in "$@"; do
    case "$arg" in
        --boost) RUN_BOOST=true ;;
        --no-setup) RUN_SETUP=false ;;
        --force) FORCE=true ;;
        -h|--help) sed -n '3,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 1 ;;
    esac
done

# Skills that Laravel Boost installs automatically but this project does not use (see SKILLS.md).
EXCLUDED_BOOST_SKILLS=(tailwindcss-development deploying-to-cloud)

info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$*" >&2; }

command -v node >/dev/null || { echo "Node.js 20+ is required (node not found)." >&2; exit 1; }
command -v npx >/dev/null || { echo "npx is required (comes with npm)." >&2; exit 1; }

# 1. Third-party skills recorded in skills-lock.json
if [[ -f skills-lock.json ]]; then
    mapfile -t SKILLS < <(node -e '
        const lock = require("./skills-lock.json");
        for (const [name, skill] of Object.entries(lock.skills || {})) console.log(`${name}\t${skill.source}`);
    ')

    for entry in "${SKILLS[@]}"; do
        name="${entry%%$'\t'*}"
        source="${entry#*$'\t'}"

        skill_dir=".agents/skills/$name"

        # Reinstalling replaces the skill directory (and its node_modules), so only do it when needed.
        if [[ -f "$skill_dir/SKILL.md" && -e ".claude/skills/$name" ]] && ! $FORCE; then
            info "Skill '$name' is already installed (use --force to reinstall)"
        else
            info "Installing skill '$name' from $source"
            npx -y skills add "$source" --skill "$name" --yes
        fi

        # Installed files are restored by this script, so keep them out of git.
        for path in ".claude/skills/$name"; do
            grep -qxF "/$path" .gitignore 2>/dev/null || printf '/%s\n' "$path" >> .gitignore
        done

        has_setup=false
        [[ -f "$skill_dir/package.json" ]] \
            && node -e "process.exit(require('./$skill_dir/package.json').scripts?.setup ? 0 : 1)" \
            && has_setup=true

        if $has_setup && [[ -d "$skill_dir/node_modules" ]] && ! $FORCE; then
            info "Setup for '$name' already done"
        elif $has_setup && ! $RUN_SETUP; then
            warn "Skipped setup for '$name'; run 'cd $skill_dir && npm run setup' before using it."
        elif $has_setup; then
            info "Running setup for '$name' (npm run setup)"
            (cd "$skill_dir" && npm run setup)
        fi
    done
else
    warn "skills-lock.json not found; no third-party skills to install."
fi

grep -qxF '/.agents/' .gitignore 2>/dev/null || printf '/.agents/\n' >> .gitignore

# 2. Laravel Boost skills (committed in .claude/skills; refresh only when asked)
if $RUN_BOOST; then
    if [[ -x vendor/bin/sail ]] && vendor/bin/sail ps --status running 2>/dev/null | grep -q laravel.test; then
        info "Refreshing Laravel Boost guidelines and skills"
        vendor/bin/sail artisan boost:update --no-interaction
        for skill in "${EXCLUDED_BOOST_SKILLS[@]}"; do
            if [[ -d ".claude/skills/$skill" ]]; then
                info "Removing excluded Boost skill '$skill'"
                rm -rf ".claude/skills/$skill"
            fi
        done
    else
        warn "Skipping --boost: start Sail first (./vendor/bin/sail up -d)."
    fi
fi

info "Done. Installed project skills:"
ls -1 .claude/skills
echo "Restart Claude Code so it picks up new skills."
