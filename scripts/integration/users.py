import os
from pathlib import Path
if Path(os.environ.get('TOTAL_INTEGRATION_FIXTURE','/')).resolve() != Path.cwd().resolve() or not (Path.cwd() / '.integration-fixture').is_file():
    raise SystemExit('Run through npm run test:integration -- --local. This case requires an isolated fixture.')
import urllib.request, urllib.error, http.cookiejar, json, uuid, secrets, subprocess
base = os.environ['TOTAL_TEST_BASE']
prefix = 'user-check-' + uuid.uuid4().hex
email = prefix + '@example.com'
manager = prefix + '-manager@example.com'
password = secrets.token_urlsafe(24)
newpassword = secrets.token_urlsafe(24)

def php(code, *args):
    return subprocess.check_output(['php', '-r', code, *args], text=True).strip()
php('require "php/models/User.php";$db=require "php/db.php";(new User($db))->create("User Test Admin",$argv[1],$argv[2]);', email, password)

class Client:

    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token = ''

    def call(self, path, data=None, method=None, status=200, csrf=True):
        headers = {'Content-Type': 'application/json'}
        if csrf:
            headers['X-CSRF-Token'] = self.token
        req = urllib.request.Request(base + path, data=json.dumps(data).encode() if data is not None else None, headers=headers, method=method)
        try:
            with self.opener.open(req) as r:
                (code, body) = (r.status, r.read())
        except urllib.error.HTTPError as r:
            (code, body) = (r.code, r.read())
        result = json.loads(body)
        assert code == status, (path, code, result)
        if result.get('csrf'):
            self.token = result['csrf']
        return result
try:
    a = Client()
    b = Client()
    a.call('/auth/me')
    a.call('/auth/login', {'email': email, 'password': password})
    aid = a.call('/auth/me')['user']['id']
    data = {'name': 'Manager Test', 'email': manager, 'role': 'Manager', 'password': password, 'password_confirmation': password}
    a.call('/users', data, 'POST', 419, False)
    a.call('/users', data, 'POST', 201)
    a.call('/users', data, 'POST', 409)
    target = next((u for u in a.call('/users')['data'] if u['email'] == manager))
    uid = target['id']
    assert target['is_active'] is True
    b.call('/auth/me')
    b.call('/auth/login', {'email': manager, 'password': password})
    b.call('/contacts')
    b.call('/subscribers')
    b.call('/users', status=403)
    b.call('/users', data, 'POST', 403)
    a.call(f'/users/{uid}/status', {'is_active': False}, 'PATCH')
    b.call('/contacts', status=401)
    b.call('/auth/me')
    b.call('/auth/login', {'email': manager, 'password': password}, status=401)
    a.call(f'/users/{uid}/status', {'is_active': True}, 'PATCH')
    b.call('/auth/login', {'email': manager, 'password': password})
    changed = {**data, 'name': 'Renamed Manager', 'password': newpassword, 'password_confirmation': newpassword}
    a.call(f'/users/{uid}', changed, 'PATCH')
    b.call('/contacts', status=401)
    b.call('/auth/me')
    b.call('/auth/login', {'email': manager, 'password': newpassword})
    a.call(f'/users/{aid}/status', {'is_active': False}, 'PATCH', 422)
    a.call(f'/users/{aid}', {'name': 'User Test Admin', 'email': email, 'role': 'Manager', 'password': '', 'password_confirmation': ''}, 'PATCH', 422)
    a.call(f'/users/{aid}', method='DELETE', status=422)
    a.call(f'/users/{uid}', method='DELETE')
    b.call('/contacts', status=401)
    assert all((u['id'] != uid for u in a.call('/users')['data']))
    print('PASS: Admin create/edit/password/ban/unban/delete, duplicate email, Manager login/content access and denied management, CSRF, session revocation, self-ban/delete/demotion guards.')
finally:
    php('$db=require "php/db.php";$q=$db->prepare("SELECT id,email FROM users WHERE email LIKE ?");$q->execute([$argv[1]."%@example.com"]);foreach($q->fetchAll() as $u){$s=$db->prepare("DELETE FROM password_resets WHERE user_id=?");$s->execute([$u["id"]]);$s=$db->prepare("DELETE FROM rate_limits WHERE identifier IN (?,?)");$s->execute([hash("sha256","login-email:".$u["email"]),hash("sha256","user-management:".$u["id"])]);}$s=$db->prepare("DELETE FROM users WHERE email LIKE ?");$s->execute([$argv[1]."%@example.com"]);', prefix)
    print('Temporary user-management accounts removed.')
