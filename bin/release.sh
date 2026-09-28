#!/usr/bin/env bash
#
# Cut a CartShare release end-to-end:
#   bump version -> update changelog -> lint + test -> release PR -> wait for
#   CI -> merge to main -> annotated tag -> push tag (triggers release.yml).
#
# Usage: bin/release.sh <X.Y.Z | major | minor | patch> [options]
#
#   -n, --notes FILE            Changelog bullets, one per line (default:
#                               generated from PRs merged since the last tag)
#   -u, --upgrade-notice TEXT   Also add a readme.txt "Upgrade Notice" entry
#   -e, --edit                  Open $EDITOR to tweak the changelog first
#   -y, --yes                   Don't ask for confirmation
#       --no-merge              Stop after opening the release PR
#       --dry-run               Only show the file changes; no git/GitHub writes
#   -h, --help                  Show this help
#
# Requirements: git, gh (authenticated), perl, composer deps installed.

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

MAIN_BRANCH=main
REMOTE=origin
VERSION_FILES=(cartshare.php readme.txt languages/cartshare.pot tests/bootstrap.php)

bold() { printf '\033[1m%s\033[0m\n' "$*"; }
step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
die() { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }
usage() { sed -n '3,19p' "$0" | sed 's/^# \{0,1\}//'; exit "${1:-0}"; }

# ---------------------------------------------------------------- arguments --

BUMP="" NOTES_FILE="" UPGRADE_NOTICE="" EDIT=0 YES=0 NO_MERGE=0 DRY_RUN=0
while [ $# -gt 0 ]; do
	case "$1" in
		-n|--notes) NOTES_FILE="${2:?--notes needs a file}"; shift 2 ;;
		-u|--upgrade-notice) UPGRADE_NOTICE="${2:?--upgrade-notice needs text}"; shift 2 ;;
		-e|--edit) EDIT=1; shift ;;
		-y|--yes) YES=1; shift ;;
		--no-merge) NO_MERGE=1; shift ;;
		--dry-run) DRY_RUN=1; shift ;;
		-h|--help) usage 0 ;;
		-*) die "unknown option: $1" ;;
		*) [ -z "$BUMP" ] || die "unexpected argument: $1"; BUMP="$1"; shift ;;
	esac
done
[ -n "$BUMP" ] || usage 1

# ---------------------------------------------------------------- versions --

CURRENT=$(perl -ne 'print $1 and exit if /^\s*\*\s*Version:\s*(\S+)/' cartshare.php)
[ -n "$CURRENT" ] || die "could not read current version from cartshare.php"

case "$BUMP" in
	major|minor|patch)
		IFS=. read -r MA MI PA <<<"${CURRENT%%-*}"
		case "$BUMP" in
			major) VERSION="$((MA + 1)).0.0" ;;
			minor) VERSION="$MA.$((MI + 1)).0" ;;
			patch) VERSION="$MA.$MI.$((PA + 1))" ;;
		esac ;;
	*) VERSION="${BUMP#v}" ;;
esac

[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]] || die "invalid version: $VERSION (expected X.Y.Z or X.Y.Z-suffix)"
[ "$VERSION" != "$CURRENT" ] || die "version $VERSION is already the current version"
[ "$(printf '%s\n%s\n' "$CURRENT" "$VERSION" | sort -V | tail -n1)" = "$VERSION" ] || die "$VERSION is lower than current $CURRENT"

TAG="v$VERSION"
BRANCH="release/$TAG"

# ---------------------------------------------------------------- preflight --

step "Preflight checks"
for cmd in git perl; do command -v "$cmd" >/dev/null || die "$cmd is required"; done
[ -x vendor/bin/phpunit ] && [ -x vendor/bin/phpcs ] || die "run 'composer install' first"
[ -z "$(git status --porcelain)" ] || die "working tree is not clean; commit or stash first"

if [ "$DRY_RUN" -eq 0 ]; then
	command -v gh >/dev/null || die "gh (GitHub CLI) is required"
	gh auth status >/dev/null 2>&1 || die "gh is not authenticated; run 'gh auth login'"
	[ "$(git branch --show-current)" = "$MAIN_BRANCH" ] || die "switch to $MAIN_BRANCH first"
	git fetch --quiet --tags "$REMOTE" "$MAIN_BRANCH"
	[ "$(git rev-parse HEAD)" = "$(git rev-parse "$REMOTE/$MAIN_BRANCH")" ] || die "$MAIN_BRANCH is not in sync with $REMOTE/$MAIN_BRANCH; pull/push first"
	git rev-parse -q --verify "refs/tags/$TAG" >/dev/null && die "tag $TAG already exists"
	git ls-remote --exit-code --heads "$REMOTE" "$BRANCH" >/dev/null 2>&1 && die "branch $BRANCH already exists on $REMOTE"
fi
echo "Current version: $CURRENT -> new version: $(bold "$VERSION")"

# ---------------------------------------------------------------- changelog --

NOTES_TMP=$(mktemp)
STAGE=preflight  # preflight -> local (branch/files changed) -> pushed

# On failure before anything is pushed, undo local changes so the repo is
# left exactly as it was. Once pushed, leave state alone and say where we are.
cleanup() {
	local rc=$?
	rm -f "$NOTES_TMP"
	[ "$rc" -ne 0 ] || return 0
	case "$STAGE" in
		local)
			echo "Rolling back local release changes..." >&2
			git checkout -q -- "${VERSION_FILES[@]}" 2>/dev/null || true
			if [ "$(git branch --show-current)" = "$BRANCH" ]; then
				git checkout -q "$MAIN_BRANCH" && git branch -q -D "$BRANCH"
			fi ;;
		pushed)
			echo "Stopped after pushing $BRANCH; see the PR, then tag $TAG on $MAIN_BRANCH once merged." >&2 ;;
	esac
}
trap cleanup EXIT

if [ -n "$NOTES_FILE" ]; then
	[ -f "$NOTES_FILE" ] || die "notes file not found: $NOTES_FILE"
	cat "$NOTES_FILE" >"$NOTES_TMP"
else
	# One bullet per first-parent commit on main since the last tag: merged
	# PRs use their title (the merge commit body), direct commits their subject.
	LAST_TAG=$(git describe --tags --abbrev=0 --match 'v[0-9]*' 2>/dev/null || true)
	git log --first-parent --format='%s%x1f%b%x1e' ${LAST_TAG:+"$LAST_TAG..HEAD"} |
		perl -0x1e -ne '
			chomp; my ($s, $b) = split /\x1f/, $_, 2; next unless defined $s;
			$s =~ s/^\s+//;
			if ($s =~ /^Merge pull request #(\d+)/) {
				my $pr = $1; ($b // "") =~ /^\s*(.+?)\s*$/m or next;
				my $t = $1; next if $t =~ /^Release v\d/i;
				print "$t (#$pr)\n";
			} elsif ($s ne "" && $s !~ /^Merge /) { print "$s\n" }
		' >"$NOTES_TMP"
fi

# Normalise to "* item" bullets and drop blank lines.
perl -ni -e 'next unless /\S/; s/^\s*[-*]\s*//; s/\s+$//; print "* $_\n"' "$NOTES_TMP"

if [ "$EDIT" -eq 1 ]; then
	"${EDITOR:-vi}" "$NOTES_TMP"
	perl -ni -e 'next unless /\S/; s/^\s*[-*]\s*//; s/\s+$//; print "* $_\n"' "$NOTES_TMP"
fi
[ -s "$NOTES_TMP" ] || die "changelog is empty; pass --notes FILE or --edit"

echo
bold "Changelog for $VERSION:"
cat "$NOTES_TMP"
[ -z "$UPGRADE_NOTICE" ] || { echo; bold "Upgrade notice:"; echo "$UPGRADE_NOTICE"; }

if [ "$YES" -eq 0 ] && [ "$DRY_RUN" -eq 0 ]; then
	echo
	read -r -p "Release $TAG (PR -> merge to $MAIN_BRANCH -> tag)? [y/N] " answer
	[[ "$answer" =~ ^[Yy]$ ]] || die "aborted"
fi

# ---------------------------------------------------------------- bump files --

[ "$DRY_RUN" -eq 1 ] || { step "Creating branch $BRANCH"; git checkout -q -b "$BRANCH"; STAGE=local; }

step "Bumping version to $VERSION"
NOTES=$(cat "$NOTES_TMP")
export NEW_VERSION="$VERSION" NOTES UPGRADE_NOTICE

perl -pi -e 's/^(\s*\*\s*Version:\s*)\S+/${1}$ENV{NEW_VERSION}/;
	s/(define\(\s*'\''CARTSHARE_VERSION'\'',\s*'\'')[^'\'']+/${1}$ENV{NEW_VERSION}/' cartshare.php tests/bootstrap.php
perl -pi -e 's/^("Project-Id-Version: .* )[0-9][^\s"\\]*(\\n")$/${1}$ENV{NEW_VERSION}${2}/' languages/cartshare.pot
perl -0pi -e '
	s/^(Stable tag:\s*)\S+/${1}$ENV{NEW_VERSION}/m;
	s/^(== Changelog ==\n\n)/$1= $ENV{NEW_VERSION} =\n$ENV{NOTES}\n\n/m or die "no == Changelog == section\n";
	if (length $ENV{UPGRADE_NOTICE}) {
		s/^(== Upgrade Notice ==\n\n)/$1= $ENV{NEW_VERSION} =\n$ENV{UPGRADE_NOTICE}\n\n/m or die "no == Upgrade Notice == section\n";
	}' readme.txt

# Every version location must now carry the new version.
for f in "${VERSION_FILES[@]}"; do
	grep -qF "$VERSION" "$f" || die "failed to update version in $f"
done
[ "$(grep -cF "$VERSION" cartshare.php)" -ge 2 ] || die "failed to update both version header and CARTSHARE_VERSION"

git --no-pager diff --stat

if [ "$DRY_RUN" -eq 1 ]; then
	git --no-pager diff
	git checkout -q -- "${VERSION_FILES[@]}"
	step "Dry run complete; changes reverted"
	exit 0
fi

# ---------------------------------------------------------------- verify --

step "Lint (WPCS)"
vendor/bin/phpcs -q .
step "Tests"
vendor/bin/phpunit

# ---------------------------------------------------------------- PR + merge --

step "Committing and opening release PR"
git commit -q -m "Release $TAG" -- "${VERSION_FILES[@]}"
git push -q -u "$REMOTE" "$BRANCH"
STAGE=pushed

PR_BODY=$(printf '## Release %s\n\n%s\n\nOpened by `bin/release.sh`. Once merged, `%s` is tagged on `%s`, which triggers the Release workflow.\n' \
	"$TAG" "$NOTES" "$TAG" "$MAIN_BRANCH")
PR_URL=$(gh pr create --base "$MAIN_BRANCH" --head "$BRANCH" --title "Release $TAG" --body "$PR_BODY")
echo "$PR_URL"

if [ "$NO_MERGE" -eq 1 ]; then
	git checkout -q "$MAIN_BRANCH"
	step "Stopped before merge (--no-merge). After merging, tag with:"
	echo "  git checkout $MAIN_BRANCH && git pull && git tag -a $TAG -m $TAG && git push $REMOTE $TAG"
	exit 0
fi

step "Waiting for CI on the release PR"
# Checks take a few seconds to register on a new PR; until then `gh pr checks`
# exits 1 ("no checks reported"). 0 = done, 8 = pending: either way, move on.
for _ in $(seq 1 30); do
	rc=0; gh pr checks "$PR_URL" >/dev/null 2>&1 || rc=$?
	[ "$rc" -eq 0 ] || [ "$rc" -eq 8 ] && break
	sleep 5
done
gh pr checks "$PR_URL" --watch --fail-fast --interval 15 ||
	die "CI failed on $PR_URL; fix it, merge manually, then tag $TAG on $MAIN_BRANCH"

step "Merging $PR_URL"
gh pr merge "$PR_URL" --merge --delete-branch
git checkout -q "$MAIN_BRANCH"
git pull -q --ff-only "$REMOTE" "$MAIN_BRANCH"
[ "$(perl -ne 'print $1 and exit if /^\s*\*\s*Version:\s*(\S+)/' cartshare.php)" = "$VERSION" ] || die "$MAIN_BRANCH does not contain version $VERSION after merge"

# ---------------------------------------------------------------- tag --

step "Tagging $TAG on $MAIN_BRANCH ($(git rev-parse --short HEAD))"
git tag -a "$TAG" -m "$TAG"
git push -q "$REMOTE" "$TAG"

REPO_URL=$(gh repo view --json url -q .url)
step "Released $TAG"
echo "Release workflow: $REPO_URL/actions/workflows/release.yml"
echo "Release page:     $REPO_URL/releases/tag/$TAG"
