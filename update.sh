#!/usr/bin/env bash

set -Eeuo pipefail
umask 022

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
cd "$ROOT_DIR"

PHP_BIN="${PHP_BIN:-php}"
LOCK_FILE="/tmp/v2board-update.lock"
BACKUP_ROOT="/www/backup/v2board-update"
SUPERVISORCTL="/www/server/panel/pyenv/bin/supervisorctl"
SUPERVISOR_CONF="/etc/supervisor/supervisord.conf"

SKIP_GIT=0
WITH_DB=0
FORCE=0
DRY_RUN=0
KEEP_DOWN=0
NO_SMOKE=0

PHASE="preflight"
RESET_DONE=0
DB_STARTED=0
DB_NEEDED=0
DEPS_STARTED=0
COMPOSER_CHANGED=0
HORIZON_WAS_RUNNING=0
ROLLBACK_FAILED=0
COMPOSER_HOME=""
COOKIE=""
COLLISION_LIST=""
SELF_COPY=""

fail() {
    echo "错误: $*" >&2
    exit 1
}

usage() {
    echo "用法: $0 [选项]"
    echo "  --skip-git   不更新 Git 代码，只重装依赖和刷新缓存"
    echo "  --with-db    即使 database/update.sql 未变化也执行数据库升级"
    echo "  --force      无新提交时也强制执行"
    echo "  --dry-run    只拉取并显示变更，不修改文件"
    echo "  --keep-down  失败并回滚后保持维护模式"
    echo "  --no-smoke   跳过部署后的冒烟测试"
    echo "  -h, --help   显示帮助"
}

run_www() {
    runuser -u www -- "$@"
}

chown_repo() {
    chown -R www:www "$ROOT_DIR" 2>/dev/null || true
}

supervisor_running() {
    [[ -x "$SUPERVISORCTL" ]] || return 1
    "$SUPERVISORCTL" -c "$SUPERVISOR_CONF" status 2>/dev/null | grep -q 'v2board.*RUNNING'
}

supervisor_program_exists() {
    [[ -x "$SUPERVISORCTL" ]] || return 1
    "$SUPERVISORCTL" -c "$SUPERVISOR_CONF" status 2>/dev/null | grep -q '^v2board:'
}

reload_fpm() {
    if [[ -x /etc/init.d/php-fpm-85 ]]; then
        /etc/init.d/php-fpm-85 reload >/dev/null 2>&1 || true
    elif systemctl list-unit-files --no-legend php-fpm-85.service 2>/dev/null | grep -q php-fpm-85; then
        systemctl reload php-fpm-85 >/dev/null 2>&1 || true
    else
        echo "警告: 未找到 php-fpm-85，请手动 reload PHP-FPM"
    fi
}

reload_webman() {
    if pgrep -f '[w]ebman.php' >/dev/null 2>&1 && [[ -f webman.php && -f cli-php.ini ]]; then
        run_www "$PHP_BIN" -c cli-php.ini webman.php reload >/dev/null 2>&1 || true
    fi
}

stop_horizon() {
    [[ "$HORIZON_WAS_RUNNING" -eq 1 && -x "$SUPERVISORCTL" ]] || return 0
    "$SUPERVISORCTL" -c "$SUPERVISOR_CONF" stop 'v2board:*' >/dev/null 2>&1 || true
}

start_horizon() {
    [[ "$HORIZON_WAS_RUNNING" -eq 1 ]] || return 0
    [[ -x "$SUPERVISORCTL" ]] || return 1
    "$SUPERVISORCTL" -c "$SUPERVISOR_CONF" start 'v2board:*' >/dev/null 2>&1 || true

    local i
    for i in $(seq 1 15); do
        supervisor_running && return 0
        sleep 1
    done

    echo "警告: Horizon 未能自动恢复"
    return 1
}

restart_horizon() {
    [[ "$HORIZON_WAS_RUNNING" -eq 1 ]] || return 0
    local i

    if supervisor_running; then
        run_www "$PHP_BIN" artisan horizon:terminate >/dev/null 2>&1 || true
        for i in $(seq 1 30); do
            supervisor_running && return 0
            sleep 1
        done
    fi

    if [[ -x "$SUPERVISORCTL" ]]; then
        "$SUPERVISORCTL" -c "$SUPERVISOR_CONF" start 'v2board:*' >/dev/null 2>&1 || true
        for i in $(seq 1 10); do
            supervisor_running && return 0
            sleep 1
        done
    fi

    echo "警告: Horizon 未能自动恢复"
    return 1
}

env_value() {
    local line
    line="$(grep -m1 -E "^[[:space:]]*$1=" .env || true)"
    [[ -n "$line" ]] || return 0
    line="${line#*=}"
    line="${line%$'\r'}"
    if [[ "$line" == \"*\" && "$line" == *\" ]]; then
        line="${line:1:${#line}-2}"
    elif [[ "$line" == \'*\' && "$line" == *\' ]]; then
        line="${line:1:${#line}-2}"
    fi
    printf '%s' "$line"
}

restore_db() {
    [[ -n "${DB_DUMP:-}" && -f "$DB_DUMP" ]] || return 0
    echo "正在恢复数据库: $DB_DUMP"
    if gunzip -c "$DB_DUMP" | MYSQL_PWD="$DB_PASSWORD" mysql \
        --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
        --default-character-set=utf8mb4 "$DB_DATABASE"; then
        echo "数据库已恢复"
    else
        echo "错误: 数据库恢复失败"
        ROLLBACK_FAILED=1
    fi
}

rollback() {
    echo
    echo "=== 开始自动回滚到 $OLD_HEAD ==="
    stop_horizon

    if [[ "$DB_STARTED" -eq 1 ]]; then
        restore_db
    fi

    if [[ "$RESET_DONE" -eq 1 ]]; then
        if ! "${GIT[@]}" reset --hard "$OLD_HEAD"; then
            echo "错误: 代码回滚失败"
            ROLLBACK_FAILED=1
        fi
        if [[ -n "$SELF_COPY" && -f "$SELF_COPY" ]]; then
            cp -a -- "$SELF_COPY" "$ROOT_DIR/update.sh"
            chmod 755 "$ROOT_DIR/update.sh"
        fi
    fi

    if [[ -d "$BACKUP_DIR/files" ]]; then
        for f in composer.json composer.lock; do
            [[ -f "$BACKUP_DIR/files/$f" ]] && cp -a -- "$BACKUP_DIR/files/$f" "$ROOT_DIR/$f"
        done
        chmod 644 composer.json composer.lock 2>/dev/null || true
    fi

    if [[ -d "$BACKUP_DIR/untracked" ]]; then
        cp -a -- "$BACKUP_DIR/untracked/." "$ROOT_DIR/" || true
    fi

    chown_repo

    if ! run_www env HOME=/tmp COMPOSER_HOME="$COMPOSER_HOME" "${COMPOSER[@]}" \
        install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress; then
        echo "错误: 回滚依赖安装失败"
        ROLLBACK_FAILED=1
    fi

    run_www "$PHP_BIN" artisan optimize:clear >/dev/null 2>&1 || true
    run_www "$PHP_BIN" artisan config:cache >/dev/null 2>&1 || true
    run_www "$PHP_BIN" artisan route:cache >/dev/null 2>&1 || true

    reload_fpm
    start_horizon || ROLLBACK_FAILED=1
    chown -R www:www storage bootstrap/cache

    if [[ "$ROLLBACK_FAILED" -eq 1 ]]; then
        echo "=== 回滚未完全成功，请保持维护模式并检查 ==="
    else
        echo "=== 代码回滚完成 ==="
    fi
}

on_exit() {
    local rc=$?
    trap - EXIT
    set +e

    if [[ "$rc" -ne 0 ]]; then
        echo
        echo "更新失败，阶段: $PHASE"

        if [[ "$DB_STARTED" -eq 1 || "$DEPS_STARTED" -eq 1 ]] || [[ "$SKIP_GIT" -eq 0 && "$RESET_DONE" -eq 1 ]]; then
            rollback
        fi

        echo "备份目录: ${BACKUP_DIR:-未创建}"
        [[ -f "${BACKUP_DIR:-}/ROLLBACK.txt" ]] && echo "回滚说明: $BACKUP_DIR/ROLLBACK.txt"
    fi

    if [[ -f storage/framework/down ]]; then
        if [[ "$rc" -ne 0 && ( "$KEEP_DOWN" -eq 1 || "$ROLLBACK_FAILED" -eq 1 ) ]]; then
            echo "站点保持维护模式"
        else
            run_www "$PHP_BIN" artisan up >/dev/null 2>&1 || true
            echo "站点已退出维护模式"
        fi
    fi

    chown -R www:www storage bootstrap/cache 2>/dev/null || true
    [[ -n "$COOKIE" && -f "$COOKIE" ]] && rm -f "$COOKIE"
    [[ -n "$COLLISION_LIST" && -f "$COLLISION_LIST" ]] && rm -f "$COLLISION_LIST"
    [[ -n "$COMPOSER_HOME" && -d "$COMPOSER_HOME" ]] && rm -rf "$COMPOSER_HOME"
    [[ -n "$SELF_COPY" && -f "$SELF_COPY" ]] && rm -f "$SELF_COPY"

    exit "$rc"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-git) SKIP_GIT=1; shift ;;
        --with-db) WITH_DB=1; shift ;;
        --force) FORCE=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        --keep-down) KEEP_DOWN=1; shift ;;
        --no-smoke) NO_SMOKE=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) usage; fail "未知参数: $1" ;;
    esac
done

[[ "$(id -u)" -eq 0 ]] || fail "请使用 root 运行"
id www >/dev/null 2>&1 || fail "系统用户 www 不存在"
command -v runuser >/dev/null 2>&1 || fail "未安装 runuser"
command -v flock >/dev/null 2>&1 || fail "未安装 flock"
command -v pgrep >/dev/null 2>&1 || fail "未安装 pgrep"
[[ -x "$SUPERVISORCTL" ]] || fail "未找到 supervisorctl: $SUPERVISORCTL"
command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP 不存在: $PHP_BIN"
[[ -f artisan ]] || fail "未找到 artisan，请确认脚本位于项目根目录"
[[ -f .env ]] || fail "未找到 .env，拒绝执行更新"
[[ -f config/v2board.php ]] || fail "未找到 config/v2board.php"

exec 9>"$LOCK_FILE"
flock -n 9 || fail "另一个更新正在运行"

SELF_COPY="/tmp/v2board-update-script.$$"
cp -a -- "$ROOT_DIR/update.sh" "$SELF_COPY"
chmod 700 "$SELF_COPY"
trap on_exit EXIT

if [[ -f composer.phar ]]; then
    COMPOSER=("$PHP_BIN" -d error_reporting=8191 composer.phar)
elif command -v composer >/dev/null 2>&1; then
    COMPOSER=(composer)
else
    fail "未找到 composer 或 composer.phar"
fi

if [[ "$NO_SMOKE" -eq 0 ]]; then
    command -v curl >/dev/null 2>&1 || fail "未安装 curl，可使用 --no-smoke 跳过测试"
fi

if [[ "$SKIP_GIT" -eq 0 ]]; then
    command -v git >/dev/null 2>&1 || fail "未安装 git"
    [[ -d .git ]] || fail "当前目录不是 Git 仓库，可使用 --skip-git 跳过代码更新"

    GIT=(git -c "safe.directory=$ROOT_DIR")
    branch="$("${GIT[@]}" symbolic-ref --short -q HEAD || true)"
    [[ -n "$branch" ]] || fail "当前处于 detached HEAD，无法自动更新"

    echo "正在获取 origin/$branch"
    "${GIT[@]}" fetch --prune origin "$branch"
    "${GIT[@]}" rev-parse --verify --quiet "origin/$branch" >/dev/null || fail "远端分支不存在: origin/$branch"

    OLD_HEAD="$("${GIT[@]}" rev-parse HEAD)"
    TARGET="$("${GIT[@]}" rev-parse "origin/$branch")"
    DIRTY=0
    if [[ -n "$("${GIT[@]}" status --porcelain --untracked-files=no -- . ':(exclude)update.sh')" ]]; then
        DIRTY=1
    fi

    if [[ -n "$("${GIT[@]}" diff --name-only "$OLD_HEAD" "$TARGET" -- composer.json)" ]]; then
        COMPOSER_CHANGED=1
    fi
    if [[ "$WITH_DB" -eq 1 ]] || [[ -n "$("${GIT[@]}" diff --name-only "$OLD_HEAD" "$TARGET" -- database/update.sql database/install.sql)" ]]; then
        DB_NEEDED=1
    fi

    if [[ "$TARGET" == "$OLD_HEAD" && "$DIRTY" -eq 0 && "$FORCE" -eq 0 && "$DB_NEEDED" -eq 0 ]]; then
        echo "已经是最新版本，无需更新"
        exit 0
    fi

    echo "旧提交: $OLD_HEAD"
    echo "目标提交: $TARGET ($branch)"
    if [[ "$TARGET" != "$OLD_HEAD" ]]; then
        "${GIT[@]}" log --oneline --no-decorate "$OLD_HEAD..$TARGET" | head -20 || true
        "${GIT[@]}" diff --stat "$OLD_HEAD" "$TARGET" || true
    fi
else
    GIT=(git -c "safe.directory=$ROOT_DIR")
    if [[ -d .git ]]; then
        OLD_HEAD="$("${GIT[@]}" rev-parse HEAD)"
    else
        OLD_HEAD="(no-git)"
    fi
    TARGET="$OLD_HEAD"
    DIRTY=0
    [[ "$WITH_DB" -eq 1 ]] && DB_NEEDED=1
    echo "已跳过 Git 更新"
fi

if [[ "$DIRTY" -eq 1 ]]; then
    echo "工作区有未提交修改，将按约定丢弃（先保存补丁到备份目录）"
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "dry-run 完成，未修改文件"
    exit 0
fi

if [[ "$DB_NEEDED" -eq 1 ]]; then
    command -v mysqldump >/dev/null 2>&1 || fail "检测到数据库升级，但未安装 mysqldump"
    command -v mysql >/dev/null 2>&1 || fail "检测到数据库升级，但未安装 mysql"
    command -v gzip >/dev/null 2>&1 || fail "检测到数据库升级，但未安装 gzip"
    [[ "$(env_value DB_CONNECTION)" == "mysql" ]] || fail "数据库升级目前只支持 mysql"
    [[ "$(df -Pk /www | awk 'NR==2{print $4}')" -ge 524288 ]] || fail "磁盘可用空间不足 512MB"
fi

mkdir -p "$BACKUP_ROOT"
BACKUP_DIR="$BACKUP_ROOT/$(date +%Y%m%d-%H%M%S)-$$"
mkdir -p "$BACKUP_DIR/files"
chmod 700 "$BACKUP_ROOT" "$BACKUP_DIR"

LOG_FILE="$BACKUP_DIR/update.log"
touch "$LOG_FILE"
chmod 600 "$LOG_FILE"
exec > >(tee -a "$LOG_FILE") 2>&1

PHASE="backup"
echo "备份目录: $BACKUP_DIR"
printf '%s\n' "$OLD_HEAD" > "$BACKUP_DIR/old_head"
printf '%s\n' "$TARGET" > "$BACKUP_DIR/target"

if [[ "$SKIP_GIT" -eq 0 ]]; then
    "${GIT[@]}" status --porcelain=v1 > "$BACKUP_DIR/git-status.txt" 2>&1 || true
    "${GIT[@]}" diff --binary HEAD > "$BACKUP_DIR/working-tree.patch" 2>&1 || true
    chmod 600 "$BACKUP_DIR/git-status.txt" "$BACKUP_DIR/working-tree.patch"
fi

for f in .env config/v2board.php composer.json composer.lock update.sh; do
    [[ -e "$f" ]] && cp -a -- "$f" "$BACKUP_DIR/files/"
done
chmod -R go-rwx "$BACKUP_DIR/files"

COLLISION_LIST="$BACKUP_DIR/untracked-list"
: > "$COLLISION_LIST"
COLLISION_COUNT=0
if [[ "$SKIP_GIT" -eq 0 && "$TARGET" != "$OLD_HEAD" ]]; then
    while IFS= read -r -d '' f; do
        if "${GIT[@]}" cat-file -e "$TARGET:$f" 2>/dev/null; then
            mkdir -p "$BACKUP_DIR/untracked"
            cp --parents -a -- "$f" "$BACKUP_DIR/untracked/"
            printf '%s\n' "$f" >> "$COLLISION_LIST"
            COLLISION_COUNT=$((COLLISION_COUNT + 1))
        fi
    done < <("${GIT[@]}" ls-files --others --exclude-standard -z)
    if [[ "$COLLISION_COUNT" -gt 0 ]]; then
        echo "已备份 $COLLISION_COUNT 个会被覆盖的未跟踪文件"
    fi
fi

DB_DUMP=""
if [[ "$DB_NEEDED" -eq 1 ]]; then
    PHASE="database-backup"
    DB_CONNECTION="$(env_value DB_CONNECTION)"
    DB_HOST="$(env_value DB_HOST)"
    DB_PORT="$(env_value DB_PORT)"
    DB_DATABASE="$(env_value DB_DATABASE)"
    DB_USERNAME="$(env_value DB_USERNAME)"
    DB_PASSWORD="$(env_value DB_PASSWORD)"
    [[ -n "$DB_HOST" && -n "$DB_DATABASE" && -n "$DB_USERNAME" ]] || fail "数据库配置不完整"

    DB_DUMP="$BACKUP_DIR/database.sql.gz"
    echo "正在备份数据库 $DB_DATABASE"
    MYSQL_PWD="$DB_PASSWORD" mysqldump \
        --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
        --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 \
        "$DB_DATABASE" | gzip -c > "$DB_DUMP"
    gzip -t "$DB_DUMP"
    chmod 600 "$DB_DUMP"
    echo "数据库备份完成: $DB_DUMP"
fi

{
    echo "旧提交: $OLD_HEAD"
    echo "目标提交: $TARGET"
    echo "备份目录: $BACKUP_DIR"
    echo
    echo "手动回滚代码："
    echo "  cd '$ROOT_DIR'"
    echo "  git reset --hard '$OLD_HEAD'"
    echo "  cp '$BACKUP_DIR/files/composer.json' '$ROOT_DIR/composer.json'"
    [[ -f "$BACKUP_DIR/files/composer.lock" ]] && echo "  cp '$BACKUP_DIR/files/composer.lock' '$ROOT_DIR/composer.lock'"
    echo "  cp '$BACKUP_DIR/files/update.sh' '$ROOT_DIR/update.sh' && chmod 755 '$ROOT_DIR/update.sh'"
    echo "  chown -R www:www '$ROOT_DIR' || true"
    echo "  runuser -u www -- env HOME=/tmp COMPOSER_HOME='$COMPOSER_HOME' ${COMPOSER[*]} install --no-dev --prefer-dist --no-interaction --optimize-autoloader"
    echo "  runuser -u www -- '$PHP_BIN' artisan optimize:clear"
    echo "  runuser -u www -- '$PHP_BIN' artisan config:cache"
    echo "  runuser -u www -- '$PHP_BIN' artisan route:cache"
    echo "  /etc/init.d/php-fpm-85 reload"
    if [[ -n "$DB_DUMP" ]]; then
        echo
        echo "恢复数据库："
        printf '  gunzip -c %q | MYSQL_PWD=%q mysql --host=%q --port=%q --user=%q %q\n' \
            "$DB_DUMP" "$DB_PASSWORD" "$DB_HOST" "$DB_PORT" "$DB_USERNAME" "$DB_DATABASE"
    fi
    echo
    echo "最后执行：runuser -u www -- '$PHP_BIN' artisan up"
} > "$BACKUP_DIR/ROLLBACK.txt"
chmod 600 "$BACKUP_DIR/ROLLBACK.txt"

if supervisor_program_exists || pgrep -f '[a]rtisan horizon' >/dev/null 2>&1; then
    HORIZON_WAS_RUNNING=1
fi

PHASE="maintenance"
SECRET="$(openssl rand -hex 8 2>/dev/null || head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')"
[[ -n "$SECRET" ]] || fail "无法生成维护模式 secret"
COOKIE="$BACKUP_DIR/cookies.txt"
chown -R www:www storage bootstrap/cache
run_www "$PHP_BIN" artisan down --secret="$SECRET" --retry=60
echo "维护模式已开启，secret: $SECRET"

PHASE="git-reset"
if [[ "$SKIP_GIT" -eq 0 ]]; then
    echo "正在重置代码到 $TARGET"
    "${GIT[@]}" reset --hard "$TARGET"
    [[ "$("${GIT[@]}" rev-parse HEAD)" == "$TARGET" ]] || fail "代码重置后 HEAD 不匹配"
    cp -a -- "$SELF_COPY" "$ROOT_DIR/update.sh"
    chmod 755 "$ROOT_DIR/update.sh"
    RESET_DONE=1
fi

PHASE="permissions"
chown_repo
COMPOSER_HOME="/tmp/v2board-composer-$RANDOM"
install -d -m 700 -o www -g www "$COMPOSER_HOME"

PHASE="composer"
DEPS_STARTED=1
if [[ "$COMPOSER_CHANGED" -eq 1 ]]; then
    echo "composer.json 有变化，执行 composer update"
    [[ -f composer.lock ]] && cp -a composer.lock "$BACKUP_DIR/files/composer.lock.update.bak"
    rm -f composer.lock
    run_www env HOME=/tmp COMPOSER_HOME="$COMPOSER_HOME" "${COMPOSER[@]}" \
        update --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress
    run_www env HOME=/tmp COMPOSER_HOME="$COMPOSER_HOME" "${COMPOSER[@]}" \
        validate --no-check-publish >/dev/null 2>&1 || true
elif [[ -f composer.lock ]]; then
    echo "正在按 composer.lock 安装依赖"
    run_www env HOME=/tmp COMPOSER_HOME="$COMPOSER_HOME" "${COMPOSER[@]}" \
        install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress
elif [[ "${ALLOW_COMPOSER_UPDATE:-0}" == "1" ]]; then
    echo "警告: composer.lock 不存在，按 ALLOW_COMPOSER_UPDATE=1 执行依赖更新"
    run_www env HOME=/tmp COMPOSER_HOME="$COMPOSER_HOME" "${COMPOSER[@]}" \
        update --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress
else
    fail "composer.lock 不存在，已拒绝执行 composer update"
fi

PHASE="cache"
run_www "$PHP_BIN" artisan optimize:clear

if [[ "$DB_NEEDED" -eq 1 ]]; then
    PHASE="database-update"
    DB_STARTED=1
    run_www "$PHP_BIN" artisan v2board:update --no-interaction --strict
else
    PHASE="config-cache"
    run_www "$PHP_BIN" artisan config:cache
fi

PHASE="route-cache"
run_www "$PHP_BIN" artisan route:cache
run_www "$PHP_BIN" artisan view:cache >/dev/null 2>&1 || true
chown -R www:www storage bootstrap/cache

PHASE="services"
reload_fpm
reload_webman
restart_horizon || fail "Horizon 未能恢复"

if [[ "$NO_SMOKE" -eq 0 ]]; then
    PHASE="smoke-test"
    SMOKE_BASE_URL="${UPDATE_SMOKE_URL:-http://127.0.0.1:12345}"
    SMOKE_BASE_URL="${SMOKE_BASE_URL%/}"
    SMOKE_HOST="${UPDATE_SMOKE_HOST:-}"
    if [[ -z "$SMOKE_HOST" ]]; then
        app_url="$(run_www "$PHP_BIN" artisan tinker --execute="echo config('v2board.app_url');" 2>/dev/null | tail -1 || true)"
        SMOKE_HOST="$(printf '%s' "$app_url" | sed -E 's#^[a-zA-Z]+://##; s#[:/].*##')"
    fi
    [[ -n "$SMOKE_HOST" ]] || SMOKE_HOST="127.0.0.1"
    secure_path="$(run_www "$PHP_BIN" artisan tinker --execute="echo config('v2board.secure_path');" 2>/dev/null | tail -1 || true)"
    server_token="$(run_www "$PHP_BIN" artisan tinker --execute="echo config('v2board.server_token');" 2>/dev/null | tail -1 || true)"
    [[ -n "$secure_path" ]] || fail "无法读取后台路径"

    echo "正在获取维护模式访问权限"
    curl -sS --max-time 10 -k -c "$COOKIE" -b "$COOKIE" -H "Host: $SMOKE_HOST" \
        -D "$BACKUP_DIR/smoke-bypass-headers.txt" \
        "$SMOKE_BASE_URL/$SECRET" -o /dev/null
    grep -qE '^HTTP/[0-9.]+ (200|302)' "$BACKUP_DIR/smoke-bypass-headers.txt" || fail "无法获取维护模式访问权限"

    echo "正在检查后台页面"
    curl -fsS --max-time 10 -k -b "$COOKIE" -H "Host: $SMOKE_HOST" \
        "$SMOKE_BASE_URL/$secure_path" -o "$BACKUP_DIR/smoke-admin.html"
    grep -q 'umi.js' "$BACKUP_DIR/smoke-admin.html" || fail "后台页面未正常返回"

    echo "正在检查前端资源"
    curl -fsS --max-time 20 -k -b "$COOKIE" -H "Host: $SMOKE_HOST" \
        "$SMOKE_BASE_URL/assets/admin/umi.js" -o "$BACKUP_DIR/smoke-umi.js"
    grep -q 'serverV2node' "$BACKUP_DIR/smoke-umi.js" || fail "umi.js 内容异常"

    node_id="$(run_www "$PHP_BIN" artisan tinker --execute="echo (int) DB::table('v2_server_v2node')->value('id');" 2>/dev/null | tail -1 || true)"
    if [[ -n "$server_token" && -n "$node_id" && "$node_id" != "0" ]]; then
        echo "正在检查 UniProxy"
        curl -fsS --max-time 10 -k -b "$COOKIE" -H "Host: $SMOKE_HOST" \
            -D "$BACKUP_DIR/smoke-headers.txt" \
            --get \
            --data-urlencode "token=$server_token" \
            --data-urlencode "node_id=$node_id" \
            --data-urlencode "node_type=v2node" \
            "$SMOKE_BASE_URL/api/v1/server/UniProxy/user" \
            -o "$BACKUP_DIR/smoke-uniproxy.json"
        grep -qi '^content-type: application/json' "$BACKUP_DIR/smoke-headers.txt" || fail "UniProxy 未返回 JSON"
    else
        echo "警告: 未取得 server_token 或 v2node 节点，跳过 UniProxy 测试"
    fi
fi

PHASE="finish"
if run_www "$PHP_BIN" artisan up; then
    echo "站点已退出维护模式"
else
    fail "无法退出维护模式"
fi
rm -rf "$COMPOSER_HOME"

find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null \
    | sort -nr | awk 'NR>10{print $2}' | xargs -r rm -rf

echo
echo "更新完成，备份目录: $BACKUP_DIR"
if grep -q 'strcasecmp' app/Http/Routes/V1/ServerRoute.php 2>/dev/null     && ! grep -q '/server/shadowsocks/save' public/assets/admin/umi.js 2>/dev/null; then
    echo "路由兼容与旧协议前端清理已包含在当前代码中"
else
    echo "注意: 当前代码未包含大写 UniProxy 兼容或旧协议前端清理"
fi
