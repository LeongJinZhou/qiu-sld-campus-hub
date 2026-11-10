#!/usr/bin/env python3
"""UAT route sweep: request every GET route as every role with real parameters and report 5xx and suspicious 404.

Usage: route_sweep.py --base http://127.0.0.1:8321 --db path/to/database.sqlite --routes routes.json --out sweep.json
The app must run with demo login enabled (APP_ENV=local) against a COPY of the database. Read-only except for the
side effects of GET endpoints (attendance scans, generated exports), so never point it at production.
"""
import argparse, collections, concurrent.futures as cf, http.cookiejar, itertools, json, re, sqlite3, urllib.error, urllib.parse, urllib.request

ROLES = {
    'guest': None,
    'alumni': 'weikeat.chong@example.com',
    'student': 'kavitha.g@example.com',
    'member': 'siti.wijaya@example.com',
    'president': 'david.francis@example.com',
    'vice_president': 'ahmad.razak@example.com',
    'secretary': 'meiling.tan@example.com',
    'treasurer': 'suresh.s@example.com',
    'event_committee': 'liwei.zhang@example.com',
    'advisor': 'advisor1.demo@qiu.edu.my',
    'approver_dean': 'dean.demo@qiu.edu.my',
    'approver_bursar': 'bursar.demo@qiu.edu.my',
    'sld_officer': 'sld.officer.demo@qiu.edu.my',
    'super_admin': 'jinzhou.leong@qiu.edu.my',
}
SKIP = re.compile(r'^(_|up$|storage|auth/|google/|dev/|calendar/feed|logout)')
TABLES = {  # route parameter -> (table, column)
    'club': ('clubs', 'slug'), 'event': ('events', 'slug'), 'post': ('posts', 'id'), 'comment': ('comments', 'id'),
    'story': ('stories', 'id'), 'highlight': ('highlights', 'id'), 'album': ('albums', 'id'), 'faq': ('club_faqs', 'id'),
    'photo': ('report_photos', 'id'), 'meeting': ('meetings', 'id'), 'document': ('documents', 'id'),
    'approval': ('approvals', 'id'), 'agm': ('agms', 'id'), 'period': ('agm_periods', 'id'), 'election': ('elections', 'id'),
    'position': ('election_positions', 'id'), 'purchase': ('event_purchases', 'id'), 'handover': ('cash_handovers', 'id'),
    'application': ('club_applications', 'id'), 'booking': ('venue_bookings', 'id'), 'fee': ('club_fees', 'id'),
    'payment': ('membership_payments', 'id'), 'receipt': ('receipts', 'id'), 'membership': ('memberships', 'id'),
    'assignment': ('role_assignments', 'id'), 'staff': ('staff', 'id'), 'user': ('users', 'id'),
    'session': ('sessions_academic', 'id'), 'message': ('assistant_messages', 'id'), 'sldRequest': ('sld_requests', 'id'),
    'entry': ('ledger_entries', 'id'), 'item': ('event_document_items', 'id'), 'helper': ('event_helpers', 'id'),
    'id': ('notifications', 'id'), 'report': ('documents', 'id'), 'section': ('documents', 'id'),
}


def candidates(db, uri, name):
    if name == 'token':
        return [r[0] for r in db.execute(f"select qr_token from {'clubs' if uri.startswith('c/') else 'users'} where qr_token is not null limit 2")]
    if name == 'code':
        t, c = ('event_registrations', 'ticket_code') if uri.startswith(('t/', 'tickets/')) else ('certificates', 'code') if 'certificates' in uri else ('signatures', 'verification_code')
        return [r[0] for r in db.execute(f'select {c} from {t} limit 2')]
    if name == 'format':
        return ['pptx', 'pdf'] if 'annual-report' in uri else (['docx', 'pdf'] if 'documents/' in uri else ['xlsx', 'pdf'])
    if name == 'kind':
        return {'attendance-qr': ['event', 'meeting', 'agm'], 'me/certificates': ['pdf'], 'purchases': ['receipt', 'payout'], 'withdrawals': ['form', 'finance', 'bankin']}.get(next((k for k in ('attendance-qr', 'me/certificates', 'purchases', 'withdrawals') if uri.startswith(k)), ''), ['1'])
    if name == 'action':
        return ['in', 'out']
    if name == 'source':
        return [r[0] for r in db.execute('select id from certificates limit 1')] or ['1']
    if name in TABLES:
        t, c = TABLES[name]
        try:
            return [str(r[0]) for r in db.execute(f'select {c} from {t} order by 1 limit 2')] or ['1']
        except sqlite3.Error:
            return ['1']
    return ['1']


def expand(db, uri):
    names = re.findall(r'\{(\w+)\??\}', uri)
    lists = [candidates(db, uri, n) or ['1'] for n in names]
    combos = list(itertools.islice(itertools.product(*lists), 4))
    out = []
    for combo in combos:
        u = uri
        for n, v in zip(names, combo):
            u = re.sub(r'\{' + n + r'\??\}', urllib.parse.quote(str(v), safe=''), u, count=1)
        out.append('/' + u.lstrip('/'))
    return out


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def client(base, email):
    jar = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect)

    def call(path, data=None, headers=None):
        try:
            r = op.open(urllib.request.Request(base + path, data=data, headers=headers or {'Accept': 'text/html'}), timeout=90)
            return r.status, r.read()
        except urllib.error.HTTPError as e:
            return e.code, e.read()
        except Exception as e:  # noqa: BLE001
            return 'ERR', str(e).encode()

    if email:
        call('/dev/login-as')
        tok = urllib.parse.unquote(next((c.value for c in jar if c.name == 'XSRF-TOKEN'), ''))
        call('/dev/login-as', json.dumps({'email': email}).encode(), {'Content-Type': 'application/json', 'X-XSRF-TOKEN': tok, 'Accept': 'application/json'})
    return call


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--base', required=True), ap.add_argument('--db', required=True), ap.add_argument('--routes', required=True), ap.add_argument('--out', required=True)
    a = ap.parse_args()
    db = sqlite3.connect(a.db)
    routes = [r for r in json.load(open(a.routes)) if 'GET' in r['method'] and not SKIP.match(r['uri']) and 'signed' not in ' '.join(r.get('middleware', []))]
    targets = sorted({(r['uri'], p) for r in routes for p in expand(db, r['uri'])})

    def run(role):
        call = client(a.base, ROLES[role])
        res = {}
        for uri, path in targets:
            code, body = call(path)
            res[path] = {'uri': uri, 'status': code}
            if code in (500, 'ERR') or (isinstance(code, int) and code >= 500):
                m = re.search(rb'"message":"([^"]{0,200})', body) or re.search(rb'<title>([^<]{0,160})', body)
                res[path]['msg'] = m.group(1).decode(errors='ignore') if m else body[:150].decode(errors='ignore')
        return role, res

    with cf.ThreadPoolExecutor(4) as ex:
        results = dict(ex.map(run, ROLES))
    summary = {role: dict(collections.Counter(str(v['status']) for v in res.values())) for role, res in results.items()}
    fivexx = [(role, p, v['status'], v.get('msg', '')) for role, res in results.items() for p, v in res.items() if v['status'] == 'ERR' or (isinstance(v['status'], int) and v['status'] >= 500)]
    # A 404 for the super admin on a URL built from real data is suspicious (a hidden record or a missing route).
    notfound = [(p, v['uri']) for p, v in results['super_admin'].items() if v['status'] == 404]
    json.dump({'urls': len(targets), 'summary': summary, 'fivexx': fivexx, 'super_admin_404': notfound, 'all': results}, open(a.out, 'w'), indent=1)
    print(f'urls={len(targets)} roles={len(ROLES)} requests={len(targets) * len(ROLES)} 5xx={len(fivexx)} super_admin_404={len(notfound)}')
    for f in fivexx[:30]:
        print('5xx', f)
    for n in notfound[:40]:
        print('404', n)


if __name__ == '__main__':
    main()
