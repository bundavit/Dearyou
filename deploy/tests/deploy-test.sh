#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_SCRIPT="$(cd -- "$SCRIPT_DIR/.." && pwd)/deploy.sh"
TEST_TEMP_BASE="$(cd -- "${TMPDIR:-/tmp}" && pwd -P)"
TEST_DIR="$(mktemp -d "$TEST_TEMP_BASE/dearyou-deploy-test.XXXXXXXX")"
cleanup() {
    case "$TEST_DIR" in
        "$TEST_TEMP_BASE"/dearyou-deploy-test.*) rm -rf -- "$TEST_DIR" ;;
        *) echo "Refusing to remove unexpected test directory: $TEST_DIR" >&2 ;;
    esac
}
trap cleanup EXIT

MOCK_BIN="$TEST_DIR/bin"
TEST_REPO="$TEST_DIR/repository with spaces"
mkdir -p "$MOCK_BIN" "$TEST_REPO/backend" "$TEST_REPO/deploy"
touch "$TEST_REPO/backend/artisan"
for fixture in dearyou-uploads.ini dearyou-scheduler.service dearyou-scheduler.timer dearyou-worker.service; do
    printf 'Synthetic deployment fixture\n' > "$TEST_REPO/deploy/$fixture"
done

# Every external mutation/network command is intercepted. The sudo mock never
# executes installation, permissions, Nginx or any host service command.
cat > "$MOCK_BIN/mock-command" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
tool="${0##*/}"
{
    printf '%s' "$tool"
    printf '|%s' "$@"
    printf '\n'
} >> "$EVENT_LOG"
case "$tool" in
    sudo)
        if [[ "${1:-}" == systemctl ]]; then
            shift
            exec "$MOCK_BIN/systemctl" "$@"
        fi
        ;;
    composer)
        [[ "$FAILING_STEP" != composer ]] || exit 42
        ;;
    php)
        if [[ "$FAILING_STEP" == maintenance && "${1:-}" == artisan && "${2:-}" == down ]]; then
            exit 42
        fi
        ;;
    systemctl)
        if [[ "${*: -1}" == dearyou-worker.service ]]; then
            if [[ "$FAILING_STEP" == worker-restart && "${1:-}" == restart ]]; then
                exit 42
            fi
            if [[ "$FAILING_STEP" == worker-inactive && "${1:-}" == is-active ]]; then
                exit 3
            fi
        fi
        ;;
esac
MOCK
for tool in git composer php sudo systemctl; do
    cp "$MOCK_BIN/mock-command" "$MOCK_BIN/$tool"
    chmod +x "$MOCK_BIN/$tool"
done

assert_contains() {
    grep -Fq -- "$2" "$1" || { echo "Missing expected output: $2" >&2; cat "$1" >&2; exit 1; }
}
assert_absent() {
    if grep -Fq -- "$2" "$1"; then
        echo "Unexpected output: $2" >&2
        cat "$1" >&2
        exit 1
    fi
}
run_case() {
    local step="$1" status=0
    EVENT_LOG="$TEST_DIR/$step.events"
    : > "$EVENT_LOG"
    PATH="$MOCK_BIN:$PATH" MOCK_BIN="$MOCK_BIN" EVENT_LOG="$EVENT_LOG" \
        FAILING_STEP="$step" REPO_DIR="$TEST_REPO" APP_DIR="$TEST_REPO/backend" \
        bash "$DEPLOY_SCRIPT" > "$TEST_DIR/$step.output" 2>&1 || status=$?
    if [[ "$step" == success ]]; then
        [[ "$status" -eq 0 ]] || { cat "$TEST_DIR/$step.output" >&2; exit 1; }
        assert_contains "$EVENT_LOG" "sudo|install|-m|0644|$TEST_REPO/deploy/dearyou-worker.service|/etc/systemd/system/dearyou-worker.service"
        assert_contains "$EVENT_LOG" 'systemctl|enable|dearyou-worker.service'
        assert_contains "$EVENT_LOG" 'systemctl|restart|dearyou-worker.service'
        assert_contains "$EVENT_LOG" 'systemctl|is-active|--quiet|dearyou-worker.service'
        assert_contains "$EVENT_LOG" 'php|artisan|up'
        assert_contains "$TEST_DIR/$step.output" 'DearYou deployment complete.'
        local active_line up_line
        active_line="$(grep -n '^systemctl|is-active|--quiet|dearyou-worker.service$' "$EVENT_LOG" | cut -d: -f1)"
        up_line="$(grep -n '^php|artisan|up$' "$EVENT_LOG" | cut -d: -f1)"
        [[ "$active_line" -lt "$up_line" ]] || { echo 'Application reopened before worker confirmation.' >&2; exit 1; }
    else
        [[ "$status" -ne 0 ]] || { echo "Expected $step to fail." >&2; exit 1; }
        assert_absent "$EVENT_LOG" 'php|artisan|up'
        assert_absent "$TEST_DIR/$step.output" 'DearYou deployment complete.'
        assert_contains "$TEST_DIR/$step.output" 'DearYou deployment failed. This script has not cleared maintenance mode.'
        if [[ "$step" == composer ]]; then
            assert_absent "$EVENT_LOG" 'systemctl|restart|dearyou-worker.service'
        elif [[ "$step" == worker-restart || "$step" == worker-inactive ]]; then
            assert_contains "$EVENT_LOG" 'systemctl|enable|dearyou-worker.service'
            assert_contains "$EVENT_LOG" 'systemctl|restart|dearyou-worker.service'
        elif [[ "$step" == maintenance ]]; then
            assert_absent "$EVENT_LOG" 'git|fetch'
        fi
    fi
    printf 'PASS: %s\n' "$step"
}

run_case success
run_case composer
run_case worker-restart
run_case worker-inactive
run_case maintenance
echo 'Deployment mock tests passed (5 cases; no network or host service changes).'
