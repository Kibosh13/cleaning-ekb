#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"
demo_url="${1:-https://kibosh13.github.io/cleaning-ekb}"
if [[ "$(git branch --show-current)" != "main" ]]; then
  echo 'Run this script from the main branch.' >&2; exit 1
fi
if [[ -n "$(git status --porcelain --untracked-files=normal)" ]]; then
  echo 'Commit source changes before publishing the demo.' >&2; exit 1
fi
command -v php >/dev/null
command -v rsync >/dev/null
mkdir -p .demo
demo_output="$(mktemp -d "$project_root/.demo/export.XXXXXX")"
trap 'rm -rf "$demo_output"' EXIT
php scripts/export-preview.php "$demo_output" "$demo_url"
# Only static allowlisted files can enter gh-pages.
if find "$demo_output" -type f \( -name '*.php' -o -name '.env*' -o -name '*.db' -o -name '*.sqlite*' \) | read -r _; then
  echo 'Refusing to publish server or private files.' >&2; exit 1
fi
repo_worktree="$project_root/.demo/gh-pages"
git -c credential.helper='!gh auth git-credential' fetch origin gh-pages
if [[ ! -d "$repo_worktree" ]]; then
  if git show-ref --verify --quiet refs/heads/gh-pages; then
    git worktree add "$repo_worktree" gh-pages
  else
    git worktree add -b gh-pages "$repo_worktree" origin/gh-pages
  fi
fi
if [[ -n "$(git -C "$repo_worktree" status --porcelain)" ]]; then
  echo 'The demo worktree has changes; inspect it before publishing.' >&2; exit 1
fi
git -C "$repo_worktree" -c credential.helper='!gh auth git-credential' pull --ff-only origin gh-pages
rsync -r --checksum --delete --exclude='.git' "$demo_output/" "$repo_worktree/"
git -C "$repo_worktree" add -A
if git -C "$repo_worktree" diff --cached --quiet; then
  echo 'The demo is already current. Nothing was committed or uploaded.'; exit 0
fi
source_commit="$(git rev-parse --short HEAD)"
git -C "$repo_worktree" commit -m "Update static demo from main $source_commit"
git -C "$repo_worktree" -c credential.helper='!gh auth git-credential' push origin gh-pages
