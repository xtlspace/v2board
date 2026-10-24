#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
cd "$ROOT_DIR"

PHP_BIN="${PHP_BIN:-php}"
SKIP_GIT=0

fail() {
    echo "错误: $*" >&2
    exit 1
}

usage() {
    echo "用法: $0 [--skip-git]"
    echo "  --skip-git  不更新 Git 代码，只更新依赖和缓存"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-git)
            SKIP_GIT=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            usage
            fail "未知参数: $1"
            ;;
    esac
done

command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP 不存在: $PHP_BIN"
[[ -f artisan ]] || fail "未找到 artisan，请确认脚本位于项目根目录"
[[ -f .env ]] || fail "未找到 .env，拒绝执行更新"
[[ -f webman.php ]] || fail "未找到 webman.php"
[[ -f cli-php.ini ]] || fail "未找到 cli-php.ini"

if [[ "$SKIP_GIT" -eq 0 ]]; then
    command -v git >/dev/null 2>&1 || fail "未安装 git"
    [[ -d .git ]] || fail "当前目录不是 Git 仓库，可使用 --skip-git 跳过代码更新"

    GIT=(git -c "safe.directory=$ROOT_DIR")
    branch="$("${GIT[@]}" symbolic-ref --short -q HEAD || true)"
    [[ -n "$branch" ]] || fail "当前处于 detached HEAD，无法自动更新"
    [[ -z "$("${GIT[@]}" status --porcelain)" ]] || fail "工作区存在未提交修改，请先处理后再更新"

    echo "正在更新代码: origin/$branch"
    "${GIT[@]}" fetch --prune origin "$branch"
    "${GIT[@]}" merge --ff-only "origin/$branch"
else
    echo "已跳过 Git 更新"
fi

if command -v composer >/dev/null 2>&1; then
    COMPOSER=(composer)
elif [[ -f composer.phar ]]; then
    COMPOSER=("$PHP_BIN" composer.phar)
else
    fail "未找到 composer 或 composer.phar"
fi

if [[ -f composer.lock ]]; then
    echo "正在安装 Composer 依赖"
    "${COMPOSER[@]}" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
elif [[ "${ALLOW_COMPOSER_UPDATE:-0}" == "1" ]]; then
    echo "警告: composer.lock 不存在，按 ALLOW_COMPOSER_UPDATE=1 执行依赖更新"
    "${COMPOSER[@]}" update --no-dev --prefer-dist --no-interaction --optimize-autoloader
else
    fail "composer.lock 不存在，已拒绝执行 composer update"
fi

echo "正在刷新 Laravel 配置缓存"
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan config:cache

echo "正在重启队列和 Webman"
"$PHP_BIN" artisan horizon:terminate >/dev/null 2>&1 || true
"$PHP_BIN" -c cli-php.ini webman.php reload >/dev/null 2>&1 || true

if [[ "$(id -u)" -eq 0 ]] && id www >/dev/null 2>&1; then
    chown -R www:www storage bootstrap/cache
fi

echo "更新完成，已跳过旧版 v2board:update 数据库升级"
