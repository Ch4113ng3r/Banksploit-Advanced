#!/bin/bash
# Run the banking-portal UI functional tests under all three run modes
# (CTF, Training, Pentest), then restore CTF.
#
# It switches modes through the instructor portal's Modes page (the same action
# an instructor takes), then runs functional_ui_test.py, which only drives the
# banking UI. The banking features should pass identically in every mode — modes
# only change the CTF/teaching scaffolding, not the banking functionality.
#
# Usage:
#   tests/ui/run_all_modes.sh
#   APP=http://127.0.0.1:9443 PANEL=http://127.0.0.1:8888 \
#     INSTRUCTOR=instructor INSTRUCTOR_PASS=instructor@123 tests/ui/run_all_modes.sh

set -u
APP="${APP:-http://127.0.0.1:9443}"
PANEL="${PANEL:-http://127.0.0.1:8888}"
INSTRUCTOR="${INSTRUCTOR:-instructor}"
INSTRUCTOR_PASS="${INSTRUCTOR_PASS:-instructor@123}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

panel_login() {
    : > "$JAR"
    curl -s -o /dev/null -c "$JAR" -b "$JAR" \
        -d "username=${INSTRUCTOR}" --data-urlencode "password=${INSTRUCTOR_PASS}" \
        "${PANEL}/login"
}

csrf() { curl -s -b "$JAR" "${PANEL}/modes" | grep -oE 'const CSRF = "[^"]+"' | sed -E 's/.*"([^"]+)"/\1/'; }

apply_mode() {
    local mode="$1" ct
    ct="$(csrf)"
    curl -s -b "$JAR" -H 'Content-Type: application/json' \
        -d "{\"action\":\"apply-mode\",\"mode\":\"${mode}\",\"csrf_token\":\"${ct}\"}" \
        "${PANEL}/modes" >/dev/null
}

panel_login
overall=0
for mode in ctf training pentest; do
    echo "============================================================"
    echo "  MODE: ${mode}"
    echo "============================================================"
    apply_mode "$mode"
    BASE="$APP" python3 "${HERE}/functional_ui_test.py"
    rc=$?
    [ $rc -ne 0 ] && overall=1
    echo
done

echo "Restoring CTF mode..."
apply_mode ctf

if [ $overall -eq 0 ]; then
    echo "ALL MODES PASSED."
else
    echo "ONE OR MORE MODES HAD FAILURES (see above)."
fi
exit $overall
