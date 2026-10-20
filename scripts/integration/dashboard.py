import os
from pathlib import Path
if Path(os.environ.get('TOTAL_INTEGRATION_FIXTURE','/')).resolve() != Path.cwd().resolve() or not (Path.cwd() / '.integration-fixture').is_file():
    raise SystemExit('Run through npm run test:integration -- --local. This case requires an isolated fixture.')
import urllib.request, urllib.error, http.cookiejar, json, uuid, subprocess, secrets, base64
base = os.environ['TOTAL_TEST_BASE']
tag = 'services-check-' + uuid.uuid4().hex
email = tag + '@example.com'
password = secrets.token_urlsafe(24)

def php(code, *args):
    return subprocess.check_output(['php', '-r', code, *args], text=True).strip()
php('require "php/models/User.php";$db=require "php/db.php";$u=new User($db);$u->create($argv[1],$argv[2],$argv[3]);$q=$db->prepare("UPDATE users SET role=? WHERE email=?");$q->execute(["Manager",$argv[2]]);', tag, email, password)

class Client:

    def __init__(self):
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token = ''

    def call(self, path, data=None, method=None, status=200, csrf=True):
        h = {'Content-Type': 'application/json'}
        if csrf:
            h['X-CSRF-Token'] = self.token
        req = urllib.request.Request(base + path, data=json.dumps(data).encode() if data is not None else None, method=method, headers=h)
        try:
            with self.op.open(req) as r:
                (code, body) = (r.status, r.read())
        except urllib.error.HTTPError as r:
            (code, body) = (r.code, r.read())
        result = json.loads(body)
        assert code == status, (path, code, result)
        if result.get('csrf'):
            self.token = result['csrf']
        return result
try:
    anon = Client()
    anon.call('/dashboard', status=401)
    c = Client()
    c.call('/auth/me')
    c.call('/auth/login', {'email': email, 'password': password})
    d = c.call('/dashboard')
    assert 'users' not in d['counts']
    assert {page['path'] for page in d['pages']} == set(json.loads(Path('public/route-manifest.json').read_text())['pages'])
    assert [m['id'] for m in d['modules']] == list(json.loads(Path('php/config/content-modules.json').read_text()))
    assert all((':' not in p['path'] and p['path'].count('/') <= 1 for p in d['pages']))
    for m in d['modules']:
        assert m['total'] == c.call('/' + m['id'])['pagination']['total']
    media = c.call('/media?unused=1')
    assert all((not m['used_by'] for m in media['data']))
    php('$db=require "php/db.php";$q=$db->prepare("UPDATE users SET role=?,session_version=session_version+1 WHERE email=?");$q->execute(["Admin",$argv[1]]);', email)
    c = Client()
    c.call('/auth/me')
    c.call('/auth/login', {'email': email, 'password': password})
    d = c.call('/dashboard')
    assert d['counts']['users'] >= 1
    print('PASS: anonymous denial, Manager/Admin user-count visibility, seven main pages without detail routes, module count parity, unattached filter.')
finally:
    php('$db=require "php/db.php";$q=$db->prepare("DELETE FROM users WHERE email=?");$q->execute([$argv[1]]);$q=$db->prepare("DELETE FROM activity_log WHERE actor_name=?");$q->execute([$argv[2]]);', email, tag)
