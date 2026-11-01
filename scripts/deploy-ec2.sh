#!/usr/bin/env bash
# Deploy a committed AgileTrack revision to the provisioned EC2 host.
# Usage: scripts/deploy-ec2.sh
# Safe local validation: DEPLOY_DRY_RUN=1 scripts/deploy-ec2.sh
# The target must already have the AgileTrack production layout and services.
# This upgrades code only; it will not provision a server or create the first admin.
set -Eeuo pipefail
umask 077

ROOT=$(git rev-parse --show-toplevel 2>/dev/null) || {
    echo 'Run this script from an AgileTrack git checkout.' >&2
    exit 1
}
cd "$ROOT"

DEPLOY_HOST=ec2-3-26-60-55.ap-southeast-2.compute.amazonaws.com
DEPLOY_USER=ec2-user
DEPLOY_KEY=$HOME/.ssh/yuki
DEPLOY_BASE=/var/www/agiletrack
DEPLOY_DOMAIN=agiletrack.reyuki.site
[[ -z "${DEPLOY_HOST_OVERRIDE:-}" ]] || DEPLOY_HOST=$DEPLOY_HOST_OVERRIDE
[[ -z "${DEPLOY_USER_OVERRIDE:-}" ]] || DEPLOY_USER=$DEPLOY_USER_OVERRIDE
[[ -z "${DEPLOY_KEY_OVERRIDE:-}" ]] || DEPLOY_KEY=$DEPLOY_KEY_OVERRIDE
DEPLOY_KEEP_RELEASES=${DEPLOY_KEEP_RELEASES:-5}
DEPLOY_KEEP_DB_BACKUPS=${DEPLOY_KEEP_DB_BACKUPS:-7}
DEPLOY_RUN_CHECKS=${DEPLOY_RUN_CHECKS:-1}
DEPLOY_DRY_RUN=${DEPLOY_DRY_RUN:-0}

for command in git composer npm ssh scp tar gzip sha256sum python3 mktemp; do
    command -v "$command" >/dev/null 2>&1 || {
        echo "Required command not found: $command" >&2
        exit 1
    }
done
if [[ "$DEPLOY_DRY_RUN" == 0 ]]; then
    [[ -r "$DEPLOY_KEY" ]] || { echo "SSH key is not readable: $DEPLOY_KEY" >&2; exit 1; }
fi
for command in date chmod rm; do
    command -v "$command" >/dev/null 2>&1 || { echo "Required command not found: $command" >&2; exit 1; }
done
[[ "$DEPLOY_KEEP_RELEASES" =~ ^[1-9][0-9]*$ ]] || { echo 'DEPLOY_KEEP_RELEASES must be a positive integer.' >&2; exit 1; }
[[ "$DEPLOY_KEEP_DB_BACKUPS" =~ ^[1-9][0-9]*$ ]] || { echo 'DEPLOY_KEEP_DB_BACKUPS must be a positive integer.' >&2; exit 1; }
[[ "$DEPLOY_RUN_CHECKS" == 0 || "$DEPLOY_RUN_CHECKS" == 1 ]] || { echo 'DEPLOY_RUN_CHECKS must be 0 or 1.' >&2; exit 1; }
[[ "$DEPLOY_DRY_RUN" == 0 || "$DEPLOY_DRY_RUN" == 1 ]] || { echo 'DEPLOY_DRY_RUN must be 0 or 1.' >&2; exit 1; }
[[ "$DEPLOY_BASE" == /var/www/agiletrack ]] || { echo 'DEPLOY_BASE must be /var/www/agiletrack on this provisioned host.' >&2; exit 1; }
[[ "$DEPLOY_DOMAIN" =~ ^[A-Za-z0-9.-]+$ && "$DEPLOY_DOMAIN" != .* && "$DEPLOY_DOMAIN" != *. && "$DEPLOY_DOMAIN" != *..* ]] || { echo 'DEPLOY_DOMAIN is invalid.' >&2; exit 1; }
[[ "$DEPLOY_HOST" =~ ^[A-Za-z0-9.-]+$ && "$DEPLOY_HOST" != .* && "$DEPLOY_HOST" != *. && "$DEPLOY_HOST" != *..* ]] || { echo 'DEPLOY_HOST is invalid.' >&2; exit 1; }
[[ "$DEPLOY_USER" =~ ^[A-Za-z_][A-Za-z0-9_-]*$ ]] || { echo 'DEPLOY_USER is invalid.' >&2; exit 1; }
if [[ "$DEPLOY_DRY_RUN" == 0 && ! -f "$HOME/.ssh/known_hosts" ]]; then
    echo 'SSH host keys must be verified and added to ~/.ssh/known_hosts before deploying.' >&2
    exit 1
fi

if [[ -n "$(git status --porcelain --untracked-files=all)" ]]; then
    echo 'Refusing to deploy or dry-run: the git working tree is not clean.' >&2
    git status --short --untracked-files=all >&2
    exit 1
fi

REVISION=$(git rev-parse HEAD)
SHORT_REVISION=${REVISION:0:12}
RELEASE_ID="$(date -u +%Y%m%dT%H%M%SZ)-$SHORT_REVISION"
CURRENT_TARGET=releases/dry-run-placeholder
if [[ "$DEPLOY_DRY_RUN" == 0 ]]; then
    CURRENT_TARGET=$(ssh -i "$DEPLOY_KEY" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes "$DEPLOY_USER@$DEPLOY_HOST" 'readlink /var/www/agiletrack/current')
    case "$CURRENT_TARGET" in
        releases/*|/var/www/agiletrack/releases/*) ;;
        *) echo 'Remote current symlink does not point into releases.' >&2; exit 1 ;;
    esac
    [[ "$CURRENT_TARGET" != *..* ]] || { echo 'Remote current symlink contains an unsafe path.' >&2; exit 1; }
    CURRENT_TARGET="releases/${CURRENT_TARGET##*/}"
fi
TARGET="$DEPLOY_USER@$DEPLOY_HOST"
SSH=(ssh -i "$DEPLOY_KEY" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes)
SCP=(scp -i "$DEPLOY_KEY" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes)

SCRATCH_ROOT="$HOME/tmp/agiletrack/deploy"
mkdir -p "$SCRATCH_ROOT"
chmod 700 "$SCRATCH_ROOT"
SCRATCH=$(mktemp -d "$SCRATCH_ROOT/$RELEASE_ID.XXXXXX")
chmod 700 "$SCRATCH"
cleanup() {
    rm -rf -- "${SCRATCH:?}"
}
trap cleanup EXIT

printf 'Revision: %s\nTarget: %s\nRelease: %s\n' "$REVISION" "$TARGET" "$RELEASE_ID"

if [[ "$DEPLOY_RUN_CHECKS" == 1 ]]; then
    echo '==> Generating ignored Wayfinder sources for checks'
    php artisan wayfinder:generate --with-form
    echo '==> Running composer ci:check'
    composer ci:check
else
    echo '==> Skipping checks because DEPLOY_RUN_CHECKS=0'
fi

[[ -d "$ROOT/node_modules" ]] || { echo 'node_modules is required; run npm install first.' >&2; exit 1; }
[[ -d "$ROOT/vendor" ]] || { echo 'Composer vendor directory is required; run composer install first.' >&2; exit 1; }
echo '==> Generating Wayfinder sources in an isolated clean checkout'
BUILD_ROOT="$SCRATCH/build"
mkdir -p "$BUILD_ROOT"
git archive "$REVISION" | tar -x -C "$BUILD_ROOT"
chmod -R u+w "$BUILD_ROOT"
mkdir -p "$BUILD_ROOT/resources/js/actions" "$BUILD_ROOT/resources/js/routes"
chmod 0755 "$BUILD_ROOT/resources/js" "$BUILD_ROOT/resources"
ln -s "$ROOT/node_modules" "$BUILD_ROOT/node_modules"
ln -s "$ROOT/vendor" "$BUILD_ROOT/vendor"
(
    cd "$BUILD_ROOT"
    env -i PATH="$PATH" HOME="$HOME" php artisan wayfinder:generate --with-form
)

echo '==> Building production assets without local .env files'
(
    cd "$BUILD_ROOT"
    env -i PATH="$PATH" HOME="$HOME" VITE_APP_NAME=AgileTrack npm run build
)
[[ -s "$BUILD_ROOT/public/build/manifest.json" ]] || { echo 'Vite build manifest is missing.' >&2; exit 1; }
[[ ! -e "$BUILD_ROOT/public/hot" ]] || { echo 'Refusing to package a Vite dev-server marker.' >&2; exit 1; }

echo '==> Packaging committed source and generated assets'
ARCHIVE="$SCRATCH/agiletrack-$RELEASE_ID.tar.gz"
git archive --format=tar --output="$SCRATCH/source.tar" "$REVISION"
mkdir -p "$SCRATCH/generated/resources/js" "$SCRATCH/generated/public"
cp -a "$BUILD_ROOT/resources/js/actions" "$SCRATCH/generated/resources/js/"
cp -a "$BUILD_ROOT/resources/js/routes" "$SCRATCH/generated/resources/js/"
cp -a "$BUILD_ROOT/public/build" "$SCRATCH/generated/public/"
chmod -R u+w "$SCRATCH/generated"
tar -rf "$SCRATCH/source.tar" -C "$SCRATCH/generated" resources/js/actions resources/js/routes public/build
gzip -c "$SCRATCH/source.tar" > "$ARCHIVE"
(
    cd "$SCRATCH"
    sha256sum "$(basename "$ARCHIVE")" > "$(basename "$ARCHIVE").sha256"
)

python3 - "$ARCHIVE" <<'PY'
import sys
import tarfile
from pathlib import PurePosixPath

with tarfile.open(sys.argv[1]) as archive:
    members = archive.getmembers()
    names = [member.name for member in members]
    unsafe = [
        member.name for member in members
        if member.issym() or member.islnk() or member.isdev()
        or PurePosixPath(member.name).is_absolute()
        or '..' in PurePosixPath(member.name).parts
    ]
    if unsafe:
        raise SystemExit(f'Release contains unsafe paths, links, or special files: {unsafe}')
    if 'public/build/manifest.json' not in names:
        raise SystemExit('Release is missing public/build/manifest.json')
    if not any(name.startswith('resources/js/actions/') for name in names):
        raise SystemExit('Release is missing generated Wayfinder actions')
    if not any(name.startswith('resources/js/routes/') for name in names):
        raise SystemExit('Release is missing generated Wayfinder routes')
    forbidden = [
        name for name in names
        if (name == '.env' or (name.startswith('.env.') and name != '.env.example'))
        or name == 'public/hot'
        or name.startswith(('.git/', '.superpowers/', 'vendor/', 'node_modules/'))
        or name.endswith(('.sqlite', '.sqlite-wal', '.sqlite-shm', '.db', '.dump', '.backup'))
    ]
    if forbidden:
        raise SystemExit(f'Release contains forbidden paths: {forbidden}')
print(f'Validated {len(names)} files; no env, dependencies, database, or dev marker.')
PY

REMOTE_SCRIPT="$SCRATCH/deploy-remote.sh"
cat > "$REMOTE_SCRIPT" <<'REMOTE'
#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

release_id=$1
base=$2
domain=$3
keep_releases=$4
keep_db_backups=$5
expected_current=$6
[[ "$expected_current" == releases/dry-run-placeholder || "$expected_current" =~ ^/?var/www/agiletrack/releases/[0-9]{8}(-|T)[0-9A-Za-z-]+$ || "$expected_current" =~ ^releases/[0-9]{8}(-|T)[0-9A-Za-z-]+$ ]] || { echo 'Invalid expected current target.' >&2; exit 2; }
[[ "$release_id" =~ ^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}$ ]] || { echo 'Invalid release identifier.' >&2; exit 2; }
[[ "$base" == /var/www/agiletrack ]] || { echo 'Deployment base must be /var/www/agiletrack.' >&2; exit 2; }
[[ "$domain" =~ ^[A-Za-z0-9.-]+$ && "$domain" != .* && "$domain" != *. && "$domain" != *..* ]] || { echo 'Invalid deployment domain.' >&2; exit 2; }
[[ "$keep_releases" =~ ^[1-9][0-9]*$ && "$keep_db_backups" =~ ^[1-9][0-9]*$ ]] || { echo 'Retention values must be positive integers.' >&2; exit 2; }
scratch="$HOME/tmp/agiletrack-deployments/$release_id"
archive="$scratch/agiletrack-$release_id.tar.gz"
mkdir -p "$scratch"
chmod 0700 "$scratch"
release="$base/releases/$release_id"
DEPLOY_SUCCESS=0
DEPLOY_LOCKED=0
cleanup_remote() {
    if [[ "${DEPLOY_SUCCESS:-0}" == 1 && "${DEPLOY_LOCKED:-0}" == 1 ]]; then
        flock -u 9 || true
        exec 9>&-
    fi
    if [[ "${DEPLOY_SUCCESS:-0}" == 1 ]]; then
        sudo -n rm -rf -- "${scratch:?}"
    else
        echo "Deployment failed; remote staging retained at $scratch for inspection." >&2
    fi
}
trap cleanup_remote EXIT
backup_dir="$base/backups/deployments"
current="$base/current"
old_target=''
activated=0
release_created=0

rollback() {
    status=$?
    trap - ERR
    set +e
    if (( activated )); then
        echo 'Deployment health check failed; restoring previous release symlink.' >&2
        sudo -n rm -f -- "$base/.current-$release_id"
        if [[ -n "$old_target" ]]; then
            sudo -n rm -f -- "$base/.current-rollback-$release_id"
            sudo -n ln -s "$old_target" "$base/.current-rollback-$release_id"
            sudo -n mv -Tf "$base/.current-rollback-$release_id" "$current"
        else
            sudo -n rm -f -- "$current"
        fi
        sudo -n systemctl restart php-fpm
        if sudo -n systemctl cat agiletrack-worker.service >/dev/null 2>&1; then
            sudo -n systemctl restart agiletrack-worker.service
        fi
    elif (( release_created )); then
        sudo -n rm -rf -- "$release"
    fi
    echo "Deployment failed with status $status. Database migrations are not automatically rolled back." >&2
    exit "$status"
}
trap rollback ERR

[[ $(stat -c '%a' "$scratch") == 700 ]] || { echo 'Remote staging directory is not private.' >&2; exit 1; }
[[ -f "$archive" && -f "$archive.sha256" ]]
(
    cd "$scratch"
    sha256sum --check "agiletrack-$release_id.tar.gz.sha256"
)
[[ -f "$base/shared/.env" ]] || { echo 'Shared production .env not found; run initial provisioning first.' >&2; exit 1; }
[[ $(stat -c '%a' "$base/shared/.env") == 640 ]] || { echo 'Shared .env permissions differ from the provisioned 0640 policy.' >&2; exit 1; }
[[ -d "$base/shared/storage" ]] || { echo 'Shared storage directory not found; run initial provisioning first.' >&2; exit 1; }
[[ -d "$base/releases" ]] || { echo 'Release directory not found; run initial provisioning first.' >&2; exit 1; }
command -v flock >/dev/null 2>&1 || { echo 'flock is required to serialize deployments.' >&2; exit 1; }
exec 9>"$base/.deploy.lock"
flock -n 9 || { echo 'Another deployment is already running.' >&2; exit 1; }
DEPLOY_LOCKED=1
trap cleanup_remote EXIT
[[ -L "$base/current" ]] || { echo 'Current release symlink not found; run initial provisioning first.' >&2; exit 1; }
current_target=$(readlink "$base/current")
case "$current_target" in
    releases/*) ;;
    "$base"/releases/*) current_target=${current_target#"$base"/} ;;
    *) echo 'Current symlink does not point into releases.' >&2; exit 1 ;;
esac
[[ "$current_target" == "$expected_current" ]] || { echo 'Remote current release changed during upload; refusing stale deployment.' >&2; exit 1; }
[[ -d "$base/$current_target" ]] || { echo 'Current release target is missing.' >&2; exit 1; }
if [[ -f "$base/current/REVISION" ]]; then
    current_revision=$(cat "$base/current/REVISION")
    [[ "${current_target##*/}" == "$current_revision" ]] || { echo 'Current release symlink does not match its revision marker.' >&2; exit 1; }
fi
nginx_conf=/etc/nginx/conf.d/$domain.conf
case "$domain" in
    agiletrack.reyuki.site) expected_cert=/etc/nginx/ssl/agiletrack.crt ;;
    *) echo "No provisioned origin certificate mapping for $domain." >&2; exit 1 ;;
esac
[[ "$nginx_conf" == /etc/nginx/conf.d/*.conf && "$nginx_conf" != *..* ]] || { echo 'Unsafe nginx vhost path.' >&2; exit 1; }
[[ -f "$nginx_conf" ]] || { echo "Expected nginx vhost not found: $nginx_conf" >&2; exit 1; }
[[ -f "$expected_cert" ]] || { echo "Expected origin TLS certificate not found: $expected_cert" >&2; exit 1; }
for command in stat grep nginx php composer flock curl pg_dump pg_restore tee; do
    command -v "$command" >/dev/null 2>&1 || { echo "Required remote command not found: $command" >&2; exit 1; }
done
grep -Fq "server_name $domain;" "$nginx_conf" || {
    echo "Expected nginx vhost does not declare server_name $domain." >&2
    exit 1
}
grep -Fq "root         $current/public;" "$nginx_conf" || {
    echo 'Nginx vhost is not configured to serve the expected current/public path.' >&2
    exit 1
}
sudo -n nginx -T >/dev/null
php -r 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);'
sudo -n systemctl is-active --quiet nginx php-fpm postgresql
[[ ! -e "$release" ]] || { echo "Release already exists: $release" >&2; exit 1; }
[[ -L "$current" && -d "$current" ]] || { echo 'Current release symlink changed or is invalid.' >&2; exit 1; }
old_target=$(readlink "$current")
[[ "$old_target" == "$expected_current" ]] || { echo 'Current release changed during deployment; refusing activation.' >&2; exit 1; }
case "$old_target" in
    releases/*) ;;
    *) echo "Current symlink points outside the releases directory: $old_target" >&2; exit 1 ;;
esac
if [[ -f "$current/REVISION" ]]; then
    current_revision=$(cat "$current/REVISION")
    [[ "$current_revision" == "${old_target##*/}" ]] || { echo 'Current release symlink does not match its revision marker.' >&2; exit 1; }
fi
[[ "$old_target" != *..* && -d "$base/$old_target" ]] || { echo 'Current release target is invalid.' >&2; exit 1; }

sudo -n install -d -o root -g root -m 0700 "$backup_dir"
sudo -n install -d -o ec2-user -g apache -m 0755 "$release"
release_created=1
trap rollback ERR
tar --no-same-owner -xzf "$archive" -C "$release"
# Replace archived placeholder directories/links with the established shared
# paths without following or modifying shared production data.
sudo -n rm -rf -- "$release/storage" "$release/public/storage"
sudo -n chown -R ec2-user:apache "$release"
release_created=0

ln -s "$base/shared/storage" "$release/storage"
ln -s "$base/shared/.env" "$release/.env"
ln -s "$base/shared/storage/app/public" "$release/public/storage"
sudo -n chown -R ec2-user:apache "$release/bootstrap/cache"
sudo -n find "$release/bootstrap/cache" -type d -exec chmod 2770 {} +
sudo -n find "$release/bootstrap/cache" -type f -exec chmod 0660 {} +

# Preserve a point-in-time snapshot before any additive schema changes.
backup_file="$backup_dir/agiletrack-$release_id.dump"
sudo -n -u postgres pg_dump --format=custom --dbname=agiletrack_db \
    | sudo -n tee "$backup_file" >/dev/null
sudo -n chown root:root "$backup_file"
sudo -n chmod 0600 "$backup_file"
sudo -n test -s "$backup_file"
sudo -n pg_restore --list "$backup_file" >/dev/null

export COMPOSER_HOME="$HOME/.config/composer"
export COMPOSER_MAX_PARALLEL_HTTP=4
cd "$release"
php -d memory_limit=512M "$(command -v composer)" install \
    --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
php "$(command -v composer)" check-platform-reqs --no-dev
php artisan migrate --force --no-interaction
php artisan optimize --no-interaction
php artisan migrate:status --no-interaction
sudo -n chown -R ec2-user:apache "$release/vendor" "$release/bootstrap/cache"
sudo -n find "$release/bootstrap/cache" -type d -exec chmod 2770 {} +
sudo -n find "$release/bootstrap/cache" -type f -exec chmod 0660 {} +
sudo -n find "$release/vendor" -type d -exec chmod 0755 {} +
sudo -n find "$release/vendor" -type f -exec chmod 0644 {} +
sudo -n find "$release/vendor" -type f -name '*.sh' -exec chmod 0755 {} +
sudo -n find "$release/vendor" -type f -perm /111 -exec chmod 0755 {} +
# Release payload is immutable after activation; writable Laravel cache remains
# the only app-owned release path shared with PHP-FPM.
find "$release" -path "$release/storage" -prune -o -path "$release/public/storage" -prune -o -path "$release/bootstrap/cache" -prune -o -type d -exec chmod 0755 {} +
find "$release" -path "$release/storage" -prune -o -path "$release/public/storage" -prune -o -path "$release/bootstrap/cache" -prune -o -type f -exec chmod 0644 {} +
find "$release" -path "$release/storage" -prune -o -path "$release/public/storage" -prune -o -path "$release/bootstrap/cache" -prune -o -type f -name '*.sh' -exec chmod 0755 {} +
find "$release" -path "$release/storage" -prune -o -path "$release/public/storage" -prune -o -path "$release/bootstrap/cache" -prune -o -type f -name artisan -exec chmod 0755 {} +
sudo -n chown -R ec2-user:apache "$release/bootstrap/cache"
sudo -n find "$release/bootstrap/cache" -type d -exec chmod 2770 {} +
sudo -n find "$release/bootstrap/cache" -type f -exec chmod 0660 {} +
sudo -n chmod 0644 "$release/REVISION"
trap - ERR
# Confirm the current target still matches the expected pre-deploy target.
[[ -L "$current" && -d "$current" ]] || { echo 'Current release symlink changed or is invalid.' >&2; exit 1; }
old_target=$(readlink "$current")
[[ "$old_target" == "$expected_current" ]] || { echo 'Current release changed during deployment; refusing activation.' >&2; exit 1; }
case "$old_target" in
    releases/*) ;;
    "$base"/releases/*) old_target=${old_target#"$base"/} ;;
    *) echo "Current symlink points outside the releases directory: $old_target" >&2; exit 1 ;;
esac
[[ "$old_target" != *..* && -d "$base/$old_target" ]] || { echo 'Current release target is invalid.' >&2; exit 1; }
if [[ -f "$current/REVISION" ]]; then
    current_revision=$(cat "$current/REVISION")
    [[ "$current_revision" == "${old_target##*/}" ]] || { echo 'Current release symlink does not match its revision marker.' >&2; exit 1; }
fi

trap rollback ERR
sudo -n rm -f -- "$base/.current-$release_id"
sudo -n ln -s "releases/$release_id" "$base/.current-$release_id"
activated=1
sudo -n mv -Tf "$base/.current-$release_id" "$current"

sudo -n systemctl restart php-fpm
if sudo -n systemctl cat agiletrack-worker.service >/dev/null 2>&1; then
    sudo -n systemctl restart agiletrack-worker.service
fi
curl --fail --silent --show-error --max-time 30 \
    --cacert "$expected_cert" \
    --resolve "$domain:443:127.0.0.1" \
    "https://$domain/up" --output "$scratch-health.html"
curl --fail --silent --show-error --max-time 30 \
    --cacert "$expected_cert" \
    --resolve "$domain:443:127.0.0.1" \
    "https://$domain/login" --output "$scratch-login.html"
grep -q 'data-page="app"' "$scratch-login.html"

sudo -n systemctl is-active --quiet nginx php-fpm postgresql
if sudo -n systemctl cat agiletrack-worker.service >/dev/null 2>&1; then
    sudo -n systemctl is-active --quiet agiletrack-worker.service
fi
# Keep the active release and the previous rollback target even if retention is 1.
mapfile -t releases < <(find "$base/releases" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r)
kept=0
for candidate in "${releases[@]}"; do
    [[ "$candidate" =~ ^[0-9]{8}(-|T)[0-9A-Za-z-]+$ ]] || continue
    if [[ "$candidate" == "$release_id" ]]; then
        kept=$((kept + 1))
        continue
    fi
    if [[ -n "$old_target" && "$candidate" == "${old_target##*/}" ]]; then
        kept=$((kept + 1))
        continue
    fi
    if (( kept < keep_releases )); then
        kept=$((kept + 1))
        continue
    fi
    sudo -n rm -rf -- "$base/releases/$candidate"
done

# Retain only the newest database snapshots after successful activation.
DEPLOY_SUCCESS=1
mapfile -t dumps < <(sudo -n find "$backup_dir" -maxdepth 1 -type f -name 'agiletrack-*.dump' -printf '%f\n' | sort -r)
for ((index = keep_db_backups; index < ${#dumps[@]}; index++)); do
    sudo -n rm -f -- "$backup_dir/${dumps[$index]}"
done

trap - ERR
echo "Deployment succeeded: $release_id"
echo "Previous release: ${old_target:-none}"
echo "Database snapshot: $backup_file"
echo "Nginx root unchanged; current symlink now targets $(readlink "$current")."
trap cleanup_remote EXIT
REMOTE

bash -n "$REMOTE_SCRIPT"
if command -v shellcheck >/dev/null 2>&1; then
    shellcheck "$0" "$REMOTE_SCRIPT"
fi

if [[ "$DEPLOY_DRY_RUN" == 1 ]]; then
    echo '==> Dry run complete; no SSH or remote changes were made.'
    exit 0
fi

# Shell-quote each argument in the remote bash command.
printf -v REMOTE_ARGS '%q ' "$RELEASE_ID" "$DEPLOY_BASE" "$DEPLOY_DOMAIN" "$DEPLOY_KEEP_RELEASES" "$DEPLOY_KEEP_DB_BACKUPS" "${CURRENT_TARGET:-releases/dry-run-placeholder}"
REMOTE_COMMAND="bash -s -- $REMOTE_ARGS"

echo '==> Uploading release archive'
# Stage archive and checksum together in a private per-release directory.
"${SSH[@]}" "$TARGET" "umask 077; mkdir -p \"\$HOME/tmp/agiletrack-deployments\"; mkdir -m 0700 \"\$HOME/tmp/agiletrack-deployments/$RELEASE_ID\""
"${SCP[@]}" "$ARCHIVE" "$ARCHIVE.sha256" "$TARGET:tmp/agiletrack-deployments/$RELEASE_ID/"

echo '==> Deploying on EC2 (migrations are backed up, never reset)'
"${SSH[@]}" "$TARGET" "$REMOTE_COMMAND" < "$REMOTE_SCRIPT"
echo "Deployment complete: https://$DEPLOY_DOMAIN (revision $SHORT_REVISION)"
