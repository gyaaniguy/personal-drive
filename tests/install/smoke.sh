#!/usr/bin/env bash
# Install smoke test: does a fresh install actually produce a working app?
#
# Two modes, one set of HTTP assertions:
#
#   script  (default)  The "Regular Installation" path from the Readme. Runs
#                      setup.sh as a normal non-root user inside a bare server
#                      container, serves the result with Apache as www-data,
#                      then re-runs setup.sh to prove a second run doesn't
#                      destroy the install.
#   docker             The "Use from Docker Hub" path. Builds the repo's own
#                      Dockerfile, runs it with the volumes the Readme tells
#                      people to mount, and restarts it to prove data survives.
#
# Slow (mostly `npm run build`) - meant for CI and for manual runs before
# touching setup.sh or the Dockerfile, not for the pre-push hook.
#
# Usage: ./tests/install/smoke.sh [script|docker]
set -uo pipefail
cd "$(dirname "$0")/../.."

MODE="${1:-script}"
PORT=8099
BASE="http://localhost:$PORT"
PASSWORD='Sm0keTest!pass'

fails=0
pass() { printf '  \033[32m OK \033[0m %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; fails=$((fails + 1)); }
step() { printf '\n\033[1;34m> %s\033[0m\n' "$1"; }
check() { # check <description> <command...>
    local msg="$1"; shift
    if "$@" >/dev/null 2>&1; then pass "$msg"; else fail "$msg"; fi
}

jar=$(mktemp)

# Both modes bind the same port, so clear either mode's leftovers first.
docker rm -f pd-install-smoke pd-docker-smoke >/dev/null 2>&1

# Wait for the container's web server to answer at all.
wait_for_http() {
    local _
    for _ in $(seq 1 60); do
        curl -s -o /dev/null "$BASE/" && return 0
        sleep 0.5
    done
    return 1
}

# The part both modes share: a working install answers these the same way,
# however it was provisioned. Creates the admin account as a side effect.
assert_app_works() {
    rm -f "$jar"

    local redirect
    redirect=$(curl -s -o /dev/null -w '%{redirect_url}' -c "$jar" "$BASE/")
    if [[ "$redirect" == *"/setup/account" ]]; then
        pass "homepage redirects to /setup/account"
    else
        fail "homepage redirects to /setup/account (got '${redirect:-no redirect}')"
    fi

    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' -c "$jar" -b "$jar" "$BASE/setup/account")
    [[ "$code" == 200 ]] && pass "setup page renders (200)" || fail "setup page renders (got $code)"

    local token status
    token=$(awk '/XSRF-TOKEN/ {print $7}' "$jar" | sed 's/%3D/=/g')
    status=$(curl -s -o /dev/null -w '%{http_code}' -b "$jar" -c "$jar" \
        -H "X-XSRF-TOKEN: $token" -H 'X-Requested-With: XMLHttpRequest' \
        -d "username=smoketest&password=$PASSWORD&password_confirmation=$PASSWORD" \
        "$BASE/setup/account")
    [[ "$status" == 30* ]] && pass "setup form accepted ($status)" || fail "setup form accepted (got $status)"

    check "admin user exists in the database" assert_one_user
}

#-----------------------------------------------------------------------------
# Mode: script - the Readme's "Regular Installation"
#-----------------------------------------------------------------------------
run_script_mode() {
    # Not `local`: the EXIT trap runs after this function has returned.
    IMAGE=personal-drive-install-smoke
    NAME=pd-install-smoke
    APP=/home/deploy/app

    # Run a command in the container as the deploy user, inside the app dir.
    dex() { docker exec -u deploy -w "$APP" "$NAME" bash -c "$1"; }
    # Queried with sqlite3, not tinker: `tinker --execute` always exits 1.
    assert_one_user() {
        dex "test \"\$(sqlite3 database/db/database.sqlite 'select count(*) from users')\" = 1"
    }
    cleanup() { docker rm -f "$NAME" >/dev/null 2>&1; rm -f "/tmp/$NAME.tar" "$jar"; }
    trap cleanup EXIT

    step "Building the bare-server image (cached after the first run)"
    docker build -q -t "$IMAGE" tests/install || { echo "image build failed"; exit 1; }

    step "Copying the working tree into a fresh container"
    # Working tree, not HEAD, so uncommitted changes to setup.sh are tested.
    # Excludes everything setup.sh is supposed to produce by itself.
    tar -cf "/tmp/$NAME.tar" \
        --exclude=.git --exclude=vendor --exclude=node_modules \
        --exclude=.agents --exclude=.omp --exclude=.ua --exclude=.idea \
        --exclude=public/build --exclude=.env --exclude=database/db \
        --exclude='storage/logs/*.log' --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' --exclude='storage/framework/cache/data/*' \
        . || exit 1
    docker rm -f "$NAME" >/dev/null 2>&1
    docker run -d --name "$NAME" -p "$PORT:80" "$IMAGE" tail -f /dev/null >/dev/null || exit 1
    docker cp "/tmp/$NAME.tar" "$NAME:/tmp/app.tar" >/dev/null || exit 1
    dex "mkdir -p $APP && tar -xf /tmp/app.tar -C $APP && chmod +x setup.sh" || exit 1

    step "Running setup.sh as the non-root 'deploy' user"
    # Answers, in prompt order: owner user, web server group, APP_URL.
    dex "printf 'deploy\nwww-data\n$BASE\n' | ./setup.sh" || fail "setup.sh exited non-zero"

    step "Checking what setup.sh produced"
    check ".env created"                         dex "test -f .env"
    check "APP_KEY generated"                    dex "grep -q '^APP_KEY=base64:' .env"
    check "APP_URL applied"                      dex "grep -q '^APP_URL=$BASE\$' .env"
    check "composer dependencies installed"      dex "test -f vendor/autoload.php"
    check "frontend built"                       dex "test -f public/build/manifest.json"
    check "sqlite database file created"         dex "test -f database/db/database.sqlite"
    check "no stale config cache left behind"    dex "test ! -f bootstrap/cache/config.php"
    check "database/db owned by deploy:www-data" dex "test \"\$(stat -c '%U:%G' database/db)\" = deploy:www-data"
    check "web server can write the database"    dex "sudo -u www-data test -w database/db/database.sqlite"
    check "web server can write storage/"        dex "sudo -u www-data test -w storage/framework/views"
    check "web server can write bootstrap/cache" dex "sudo -u www-data test -w bootstrap/cache"

    step "Serving it with Apache as www-data (like a real install)"
    docker exec -d "$NAME" apache2-foreground
    wait_for_http || fail "Apache never answered on $BASE"
    assert_app_works

    step "Re-running setup.sh on the installed app (must not destroy it)"
    local key_before key_after
    key_before=$(dex "grep '^APP_KEY=' .env" | tr -d '\r')
    dex "printf 'deploy\nwww-data\n' | ./setup.sh" || fail "second setup.sh run exited non-zero"
    key_after=$(dex "grep '^APP_KEY=' .env" | tr -d '\r')
    if [[ -n "$key_before" && "$key_before" == "$key_after" ]]; then
        pass "APP_KEY preserved"
    else
        fail "APP_KEY preserved (before='$key_before' after='$key_after')"
    fi
    check "APP_URL preserved"        dex "grep -q '^APP_URL=$BASE\$' .env"
    check "admin user still present" assert_one_user
}

#-----------------------------------------------------------------------------
# Mode: docker - the Readme's docker-compose instructions
#-----------------------------------------------------------------------------
run_docker_mode() {
    # Not `local`: the EXIT trap runs after this function has returned.
    IMAGE=personal-drive-docker-smoke
    NAME=pd-docker-smoke
    VOLUME=pd-docker-smoke-data
    APP=/var/www/html/personal-drive
    STORE=/var/www/html/personal-drive-storage-folder
    hostdir=$(mktemp -d)
    chmod 777 "$hostdir"   # the Readme says the host directory must be writable

    dex() { docker exec -w "$APP" "$NAME" bash -c "$1"; }
    assert_one_user() {
        dex "test \"\$(sqlite3 database/db/database.sqlite 'select count(*) from users')\" = 1"
    }
    cleanup() {
        docker rm -f "$NAME" >/dev/null 2>&1
        docker volume rm "$VOLUME" >/dev/null 2>&1
        # The container writes into the bind mount as root, so files we don't
        # own can remain; /tmp cleanup gets them.
        rm -rf "$hostdir" 2>/dev/null
        rm -f "$jar"
    }
    trap cleanup EXIT

    step "Building the repo's Dockerfile"
    docker build -q -t "$IMAGE" . || { echo "image build failed"; exit 1; }
    pass "image builds"

    step "Running it with the volumes from the Readme"
    docker rm -f "$NAME" >/dev/null 2>&1
    docker volume rm "$VOLUME" >/dev/null 2>&1
    docker run -d --name "$NAME" -p "$PORT:80" \
        -v "$hostdir:$STORE" -v "$VOLUME:$APP/database/db" \
        -e DISABLE_HTTPS=true "$IMAGE" >/dev/null || exit 1
    wait_for_http || fail "container never answered on $BASE"

    check "entrypoint generated an APP_KEY" dex "grep -q '^APP_KEY=base64:' .env"
    check "web server can write the mounted storage folder" \
        dex "sudo -u www-data test -w $STORE 2>/dev/null || su www-data -s /bin/sh -c 'test -w $STORE'"
    check "web server can write the mounted database volume" \
        dex "su www-data -s /bin/sh -c 'test -w database/db'"

    assert_app_works

    step "Restarting the container (data must survive)"
    local key_before key_after
    key_before=$(dex "grep '^APP_KEY=' .env" | tr -d '\r')
    docker restart "$NAME" >/dev/null || fail "container failed to restart"
    wait_for_http || fail "container never answered after restart"
    key_after=$(dex "grep '^APP_KEY=' .env" | tr -d '\r')
    if [[ -n "$key_before" && "$key_before" == "$key_after" ]]; then
        pass "APP_KEY survived the restart"
    else
        fail "APP_KEY survived the restart (before='$key_before' after='$key_after')"
    fi
    check "admin user survived the restart" assert_one_user

    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")
    [[ "$code" == 30* || "$code" == 200 ]] && pass "app still serves after restart ($code)" \
        || fail "app still serves after restart (got $code)"
}

case "$MODE" in
    script) run_script_mode ;;
    docker) run_docker_mode ;;
    *) echo "usage: $0 [script|docker]"; exit 2 ;;
esac

if [[ $fails -eq 0 ]]; then
    printf '\n\033[1;32mOK - install smoke test passed (%s mode)\033[0m\n' "$MODE"
else
    printf '\n\033[1;31mFAIL - %d check(s) failed (%s mode)\033[0m\n' "$fails" "$MODE"
fi
exit $((fails > 0))
