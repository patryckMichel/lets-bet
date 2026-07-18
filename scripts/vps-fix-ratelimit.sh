#!/usr/bin/env bash
set -euo pipefail
export PATH="/usr/local/go/bin:$PATH"
cd /opt/bet

python3 - <<'PY'
from pathlib import Path

# Fix redis_limiter.go type assertions
p = Path('internal/infrastructure/ratelimit/redis_limiter.go')
t = p.read_text()
old = """\t// Parse Lua script result
\tresultSlice := result.([]any)
\tallowed := resultSlice[0].(int64) == 1
\tremaining := resultSlice[1].(int64)
\tvar resetTime time.Time
\tif len(resultSlice) > 3 {
\t\tresetTimestamp := resultSlice[3].(int64)
\t\tresetTime = time.Unix(0, resetTimestamp)
\t} else {
\t\tresetTime = windowEnd
\t}
"""
new = """\t// Parse Lua script result (Redis may return int64 or string)
\tresultSlice := result.([]any)
\tallowed := toInt64(resultSlice[0]) == 1
\tremaining := toInt64(resultSlice[1])
\tvar resetTime time.Time
\tif len(resultSlice) > 3 {
\t\tresetTimestamp := toInt64(resultSlice[3])
\t\tresetTime = time.Unix(0, resetTimestamp)
\t} else {
\t\tresetTime = windowEnd
\t}
"""
if old in t:
    t = t.replace(old, new)
elif 'toInt64(resultSlice[0])' in t:
    print('redis_limiter already patched (parse)')
else:
    raise SystemExit('redis_limiter parse block not found')

if 'strconv' not in t:
    t = t.replace('\n\t"net"\n\t"time"\n', '\n\t"net"\n\t"strconv"\n\t"time"\n')

helper = '''
func toInt64(v any) int64 {
\tswitch n := v.(type) {
\tcase int64:
\t\treturn n
\tcase int:
\t\treturn int64(n)
\tcase float64:
\t\treturn int64(n)
\tcase string:
\t\tparsed, err := strconv.ParseInt(n, 10, 64)
\t\tif err != nil {
\t\t\treturn 0
\t\t}
\t\treturn parsed
\tdefault:
\t\treturn 0
\t}
}
'''
if 'func toInt64(' not in t:
    t = t.rstrip() + '\n' + helper + '\n'

p.write_text(t)
print('patched redis_limiter.go')

# Fix getClientIP for IPv6
p = Path('internal/infrastructure/ratelimit/middleware.go')
t = p.read_text()
old = '''func getClientIP(r *http.Request, trustedProxyCIDRs []string) string {
\t// Remove port from RemoteAddr for comparison
\tremoteAddr := r.RemoteAddr
\tif idx := strings.LastIndex(remoteAddr, ":"); idx != -1 {
\t\tremoteAddr = remoteAddr[:idx]
\t}

\t// Check if request comes from trusted proxy
\tisFromTrustedProxy := isTrustedProxy(remoteAddr, trustedProxyCIDRs)

\tif isFromTrustedProxy {
\t\t// Only trust X-Forwarded-For from trusted proxies
\t\tif xff := r.Header.Get("X-Forwarded-For"); xff != "" {
\t\t\t// X-Forwarded-For can contain multiple IPs, take the first one (original client)
\t\t\tfor i, c := range xff {
\t\t\t\tif c == ',' {
\t\t\t\t\treturn strings.TrimSpace(xff[:i])
\t\t\t\t}
\t\t\t}
\t\t\treturn xff
\t\t}

\t\t// Fall back to X-Real-IP from trusted proxies
\t\tif xri := r.Header.Get("X-Real-IP"); xri != "" {
\t\t\treturn xri
\t\t}
\t}

\t// Default to RemoteAddr if not from trusted proxy or headers invalid
\tif idx := strings.LastIndex(r.RemoteAddr, ":"); idx != -1 {
\t\treturn r.RemoteAddr[:idx]
\t}
\treturn r.RemoteAddr
}
'''
new = '''func getClientIP(r *http.Request, trustedProxyCIDRs []string) string {
\tremoteAddr, _, err := net.SplitHostPort(r.RemoteAddr)
\tif err != nil {
\t\tremoteAddr = r.RemoteAddr
\t}

\t// Check if request comes from trusted proxy
\tisFromTrustedProxy := isTrustedProxy(remoteAddr, trustedProxyCIDRs)

\tif isFromTrustedProxy {
\t\t// Only trust X-Forwarded-For from trusted proxies
\t\tif xff := r.Header.Get("X-Forwarded-For"); xff != "" {
\t\t\t// X-Forwarded-For can contain multiple IPs, take the first one (original client)
\t\t\tfor i, c := range xff {
\t\t\t\tif c == ',' {
\t\t\t\t\treturn strings.TrimSpace(xff[:i])
\t\t\t\t}
\t\t\t}
\t\t\treturn xff
\t\t}

\t\t// Fall back to X-Real-IP from trusted proxies
\t\tif xri := r.Header.Get("X-Real-IP"); xri != "" {
\t\t\treturn xri
\t\t}
\t}

\treturn remoteAddr
}
'''
if old in t:
    t = t.replace(old, new)
    p.write_text(t)
    print('patched middleware.go')
elif 'SplitHostPort(r.RemoteAddr)' in t:
    print('middleware already patched')
else:
    raise SystemExit('getClientIP block not found')
PY

go build -o bin/gateway ./cmd/gateway
systemctl restart bet-gateway
sleep 1
curl -sS http://127.0.0.1:8080/healthz
echo
curl -sS -H 'Host: lestber369.com' http://127.0.0.1/healthz
echo
echo FIX_DONE
