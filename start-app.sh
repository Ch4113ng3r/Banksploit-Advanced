#!/bin/bash
# =============================================================================
#  BankSploit - local deployment script for macOS and Linux
#  Usage: ./start-app.sh [setup|start|stop|restart|status|reset|restore|reset-full|help]
#
#  Settings you can override with environment variables:
#    MYSQL_ROOT_PASS   MySQL root password. Used by setup and reset-full only;
#                      you are prompted if it isn't set. It is never saved.
#    BIND_HOST         Banking app address (default 127.0.0.1).
#                      Use 0.0.0.0 to open it to your network for an event.
#    SUPERADMIN_BIND   Instructor panel address (default 127.0.0.1).
#    APP_PORT, SUPERADMIN_PORT, DB_HOST, DB_PORT, PHP_WORKERS
# =============================================================================

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="${PROJECT_DIR}/src"
SUPERADMIN_SRC="${PROJECT_DIR}/src-superadmin"
SQL_DIR="${PROJECT_DIR}/sql"
LOCAL_DIR="${PROJECT_DIR}/.local"

APP_PID_FILE="${LOCAL_DIR}/php.pid"
APP_LOG="${LOCAL_DIR}/php-server.log"
SUPERADMIN_PID_FILE="${LOCAL_DIR}/superadmin-php.pid"
SUPERADMIN_LOG="${LOCAL_DIR}/superadmin-php-server.log"
SUBMISSIONS_DUMP="${LOCAL_DIR}/submissions-before-reset.sql"

APP_PORT="${APP_PORT:-9443}"
SUPERADMIN_PORT="${SUPERADMIN_PORT:-8888}"
BIND_HOST="${BIND_HOST:-127.0.0.1}"
SUPERADMIN_BIND="${SUPERADMIN_BIND:-127.0.0.1}"
PHP_WORKERS="${PHP_WORKERS:-8}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="banksploit"
DB_USER="banksploit"
DB_PASS="banksploit_pass"

# Load order is deliberate; do not replace this with sql/*.sql.
# sql/add-100-extended-users.sql is excluded on purpose: it deletes the BS1004xx
# users created by 04-bulk-users-100-extended.sql and re-adds them with less data.
SQL_FILES="
01-schema.sql
02-seed-data.sql
03-bulk-users-100.sql
03-ctf-flags.sql
03-feature-toggles.sql
04-bulk-users-100-extended.sql
04-ctf-submissions.sql
migration-add-username-to-ctf-flags.sql
migration-add-replacement-status.sql
"

# ----- Output helpers --------------------------------------------------------
ok()   { printf '[OK] %s\n' "$1"; }
warn() { printf '[!]  %s\n' "$1"; }
err()  { printf '[X]  %s\n' "$1" >&2; }
info() { printf '[*]  %s\n' "$1"; }

# ----- Dependencies ----------------------------------------------------------
find_mysql_tool() {
    local tool="$1" dir
    if command -v "$tool" >/dev/null 2>&1; then
        command -v "$tool"
        return 0
    fi
    for dir in /usr/local/mysql/bin /opt/homebrew/opt/mysql@8.0/bin \
               /opt/homebrew/opt/mysql/bin /usr/local/opt/mysql@8.0/bin; do
        if [[ -x "${dir}/${tool}" ]]; then
            printf '%s\n' "${dir}/${tool}"
            return 0
        fi
    done
    return 1
}

check_deps() {
    if ! command -v php >/dev/null 2>&1; then
        err "PHP was not found on your PATH."
        exit 1
    fi
    # Captured first: with pipefail, grep -q closing the pipe early reads as failure.
    local php_modules
    php_modules="$(php -m 2>/dev/null)"
    if ! grep -qi '^pdo_mysql$' <<< "$php_modules"; then
        err "PHP is missing the pdo_mysql extension."
        exit 1
    fi
    if ! MYSQL_BIN="$(find_mysql_tool mysql)"; then
        err "The mysql client was not found. Add it to your PATH and try again."
        exit 1
    fi
    MYSQLDUMP_BIN="$(find_mysql_tool mysqldump || true)"
}

# ----- Environment and secrets -----------------------------------------------
load_flag_secret() {
    # src/core/Flags.php reads this file. Never export the secret: the phpinfo()
    # lab prints the environment, so it would be public again.
    local dir="${HOME}/.banksploit" file="${HOME}/.banksploit/flag_secret"
    if [[ ! -s "$file" ]]; then
        mkdir -p "$dir"
        chmod 700 "$dir"
        (umask 077; openssl rand -hex 32 > "$file")
        info "Generated a new flag secret at ${file}"
    fi
    unset FLAG_SECRET
}

export_app_env() {
    export DB_HOST DB_PORT DB_NAME DB_USER DB_PASS
    export APP_SCHEME="http" APP_HOST="localhost" APP_PORT
    export APP_ENV="development" APP_DEBUG="true"
    export JWT_SECRET="insecure_jwt_secret_for_training"
    export SUPERADMIN_PORT
    export PHP_CLI_SERVER_WORKERS="$PHP_WORKERS"
    load_flag_secret
}

# ----- MySQL helpers ---------------------------------------------------------
# Passwords go through MYSQL_PWD so they never appear in the process list.
app_mysql() {
    MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" -u"$DB_USER" -h"$DB_HOST" -P"$DB_PORT" \
        --protocol=TCP --default-character-set=utf8mb4 "$@"
}

root_mysql() {
    MYSQL_PWD="$MYSQL_ROOT_PASS" "$MYSQL_BIN" -uroot -h"$DB_HOST" -P"$DB_PORT" \
        --protocol=TCP --default-character-set=utf8mb4 "$@"
}

require_mysql_running() {
    # Server version comes back in the handshake, so nc confirms MySQL is listening.
    if ! nc -z "$DB_HOST" "$DB_PORT" >/dev/null 2>&1; then
        err "MySQL isn't reachable at ${DB_HOST}:${DB_PORT}."
        if [[ -x /usr/local/mysql/support-files/mysql.server ]]; then
            err "Start it with: sudo /usr/local/mysql/support-files/mysql.server start"
        fi
        exit 1
    fi
}

get_root_password() {
    if [[ -z "${MYSQL_ROOT_PASS:-}" ]]; then
        read -r -s -p "MySQL root password: " MYSQL_ROOT_PASS
        printf '\n'
    fi
    if ! root_mysql -e "SELECT 1" >/dev/null 2>&1; then
        err "Could not log in to MySQL as root with that password."
        exit 1
    fi
}

database_is_loaded() {
    [[ "$(root_mysql -N -e "SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='users'")" != "0" ]]
}

create_database_and_user() {
    info "Creating database '${DB_NAME}' and user '${DB_USER}'..."
    root_mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
SQL
}

load_sql_files() {
    local file
    for file in $SQL_FILES; do
        info "  Loading ${file}"
        if ! app_mysql "$DB_NAME" < "${SQL_DIR}/${file}" >/dev/null; then
            err "Failed while loading ${file}. Setup stopped."
            exit 1
        fi
    done
}

seed_flags() {
    info "Seeding CTF flags..."
    php "${SRC_DIR}/scripts/seed_flags.php"
}

# ----- Process helpers -------------------------------------------------------
is_running() {
    local pid_file="$1" pid
    [[ -s "$pid_file" ]] || return 1
    pid="$(cat "$pid_file")"
    kill -0 "$pid" 2>/dev/null || return 1
    # Guards against a stale PID file whose number now belongs to another program.
    [[ "$(ps -p "$pid" -o command= 2>/dev/null)" == *php* ]]
}

port_in_use() {
    lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1
}

wait_for_http() {
    local url="$1" attempt
    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        if curl -s -o /dev/null --max-time 5 "$url"; then
            return 0
        fi
        sleep 1
    done
    return 1
}

start_server() {
    # args: label, bind, port, docroot, router, workdir, pid file, log file
    local label="$1" bind="$2" port="$3" docroot="$4" router="$5" workdir="$6" pid_file="$7" log="$8"

    if is_running "$pid_file"; then
        ok "${label} is already running (PID $(cat "$pid_file"))"
        return 0
    fi
    if port_in_use "$port"; then
        err "Port ${port} is already in use, so ${label} can't start."
        exit 1
    fi

    (
        cd "$workdir"
        nohup php -d max_execution_time=300 -d memory_limit=256M \
            -S "${bind}:${port}" -t "$docroot" "$router" >> "$log" 2>&1 &
        echo $! > "$pid_file"
    )

    if wait_for_http "http://127.0.0.1:${port}/login" && is_running "$pid_file"; then
        ok "${label} started on ${bind}:${port} (PID $(cat "$pid_file"))"
    else
        err "${label} failed to start. Check ${log}"
        exit 1
    fi
}

stop_server() {
    local label="$1" pid_file="$2" pid
    if is_running "$pid_file"; then
        pid="$(cat "$pid_file")"
        # Stop the worker processes as well as the parent.
        pkill -TERM -P "$pid" 2>/dev/null || true
        kill "$pid" 2>/dev/null || true
        ok "${label} stopped"
    else
        info "${label} wasn't running"
    fi
    rm -f "$pid_file"
}

# ----- Commands --------------------------------------------------------------
cmd_setup() {
    check_deps
    require_mysql_running
    mkdir -p "$LOCAL_DIR"
    export_app_env
    get_root_password

    if database_is_loaded; then
        warn "Database '${DB_NAME}' is already set up, so nothing was changed."
        warn "To rebuild it from scratch, run: ./start-app.sh reset-full"
        return 0
    fi

    create_database_and_user
    info "Loading schema and seed data..."
    load_sql_files
    seed_flags
    ok "Setup complete. Run: ./start-app.sh start"
}

cmd_start() {
    check_deps
    require_mysql_running
    mkdir -p "$LOCAL_DIR"
    export_app_env

    if ! app_mysql "$DB_NAME" -N -e "SELECT 1 FROM users LIMIT 1" >/dev/null 2>&1; then
        err "The database isn't set up yet. Run: ./start-app.sh setup"
        exit 1
    fi

    start_server "Banking app" "$BIND_HOST" "$APP_PORT" "$SRC_DIR" \
        "${PROJECT_DIR}/router.php" "$PROJECT_DIR" "$APP_PID_FILE" "$APP_LOG"
    start_server "Instructor panel" "$SUPERADMIN_BIND" "$SUPERADMIN_PORT" "$SUPERADMIN_SRC" \
        "${SUPERADMIN_SRC}/router.php" "$SUPERADMIN_SRC" "$SUPERADMIN_PID_FILE" "$SUPERADMIN_LOG"

    printf '\n'
    ok "BankSploit is running"
    printf '  Banking app:       http://localhost:%s\n' "$APP_PORT"
    printf '  Instructor panel:  http://localhost:%s\n' "$SUPERADMIN_PORT"
    if [[ "$BIND_HOST" == "0.0.0.0" || "$SUPERADMIN_BIND" == "0.0.0.0" ]]; then
        warn "This deliberately vulnerable app is open to your network."
    fi
}

cmd_stop() {
    stop_server "Instructor panel" "$SUPERADMIN_PID_FILE"
    stop_server "Banking app" "$APP_PID_FILE"
    info "MySQL was left running because it's a shared system service."
}

cmd_status() {
    if nc -z "$DB_HOST" "$DB_PORT" >/dev/null 2>&1; then
        ok "MySQL: running on ${DB_HOST}:${DB_PORT}"
    else
        warn "MySQL: not reachable on ${DB_HOST}:${DB_PORT}"
    fi
    if is_running "$APP_PID_FILE"; then
        ok "Banking app: running at http://localhost:${APP_PORT} (PID $(cat "$APP_PID_FILE"))"
    else
        warn "Banking app: stopped"
    fi
    if is_running "$SUPERADMIN_PID_FILE"; then
        ok "Instructor panel: running at http://localhost:${SUPERADMIN_PORT} (PID $(cat "$SUPERADMIN_PID_FILE"))"
    else
        warn "Instructor panel: stopped"
    fi
}

cmd_reset() {
    local choice
    check_deps
    require_mysql_running
    printf '  1 - Reset scoreboard (back up submissions first)\n'
    printf '  2 - Reset scoreboard (permanent, no backup)\n'
    printf '  3 - Cancel\n'
    read -r -p "  Choose [1/2/3]: " choice
    case "$choice" in
        1)
            app_mysql "$DB_NAME" <<'SQL'
CREATE TABLE IF NOT EXISTS ctf_submissions_backup LIKE ctf_submissions;
TRUNCATE ctf_submissions_backup;
INSERT INTO ctf_submissions_backup SELECT * FROM ctf_submissions;
DELETE FROM ctf_submissions;
SQL
            ok "Submissions backed up and scoreboard cleared."
            ;;
        2)
            app_mysql "$DB_NAME" -e "DELETE FROM ctf_submissions"
            ok "Scoreboard cleared with no backup."
            ;;
        *)
            warn "Cancelled."
            ;;
    esac
}

cmd_restore() {
    local count confirm
    check_deps
    require_mysql_running
    count="$(app_mysql "$DB_NAME" -N -e "SELECT COUNT(*) FROM ctf_submissions_backup" 2>/dev/null || echo 0)"
    if [[ "$count" == "0" ]]; then
        warn "No backup found."
        return 0
    fi
    read -r -p "  Restore ${count} backed-up submissions? [y/N]: " confirm
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        app_mysql "$DB_NAME" -e "INSERT IGNORE INTO ctf_submissions SELECT * FROM ctf_submissions_backup"
        ok "Submissions restored."
    else
        warn "Cancelled."
    fi
}

cmd_reset_full() {
    local choice confirm keep=0
    check_deps
    require_mysql_running
    mkdir -p "$LOCAL_DIR"
    export_app_env

    printf '  1 - Full reset (delete everything)\n'
    printf '  2 - Full reset (keep submitted flags)\n'
    printf '  3 - Cancel\n'
    read -r -p "  Choose [1/2/3]: " choice
    case "$choice" in
        1) ;;
        2) keep=1 ;;
        *) warn "Cancelled."; return 0 ;;
    esac

    read -r -p "  Type 'yes' to drop database '${DB_NAME}': " confirm
    if [[ "$confirm" != "yes" ]]; then
        warn "Cancelled."
        return 0
    fi

    get_root_password

    if [[ "$keep" == "1" ]]; then
        if [[ -z "$MYSQLDUMP_BIN" ]]; then
            err "mysqldump is needed to keep submissions, but it wasn't found."
            exit 1
        fi
        # Saved to a file because a backup table would be dropped with the database.
        info "Saving submissions to ${SUBMISSIONS_DUMP}"
        MYSQL_PWD="$DB_PASS" "$MYSQLDUMP_BIN" -u"$DB_USER" -h"$DB_HOST" -P"$DB_PORT" \
            --protocol=TCP --no-tablespaces --no-create-info --complete-insert \
            --insert-ignore --skip-add-locks "$DB_NAME" ctf_submissions > "$SUBMISSIONS_DUMP"
    fi

    info "Dropping database '${DB_NAME}'..."
    root_mysql -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`"
    create_database_and_user
    info "Loading schema and seed data..."
    load_sql_files
    seed_flags

    if [[ "$keep" == "1" ]]; then
        app_mysql "$DB_NAME" < "$SUBMISSIONS_DUMP"
        ok "Submissions restored."
    fi
    ok "Full reset complete."
}

cmd_help() {
    cat <<'HELP'

  BankSploit - local deployment

  Usage: ./start-app.sh <command>

    setup       First-time setup: create the database and user, load data
    start       Start the banking app and instructor panel
    stop        Stop both servers (MySQL keeps running)
    restart     Stop, then start
    status      Show what's running
    reset       Clear the scoreboard, with an optional backup
    restore     Restore submissions from that backup
    reset-full  Drop and rebuild the whole database
    help        Show this message

  Open to your network for an event:
    BIND_HOST=0.0.0.0 ./start-app.sh start

HELP
}

case "${1:-help}" in
    setup)      cmd_setup ;;
    start)      cmd_start ;;
    stop)       cmd_stop ;;
    restart)    cmd_stop; cmd_start ;;
    status)     cmd_status ;;
    reset)      cmd_reset ;;
    restore)    cmd_restore ;;
    reset-full) cmd_reset_full ;;
    help|-h|--help) cmd_help ;;
    *)
        err "Unknown command: $1"
        cmd_help
        exit 1
        ;;
esac
