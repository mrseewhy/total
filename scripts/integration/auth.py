import os
from pathlib import Path
if Path(os.environ.get('TOTAL_INTEGRATION_FIXTURE','/')).resolve() != Path.cwd().resolve() or not (Path.cwd() / '.integration-fixture').is_file():
    raise SystemExit('Run through npm run test:integration -- --local. This case requires an isolated fixture.')
import urllib.request, urllib.error, http.cookiejar, json, uuid, secrets, subprocess
base = os.environ['TOTAL_TEST_BASE']
email = 'auth-check-' + uuid.uuid4().hex + '@example.com'
password = secrets.token_urlsafe(24)
newpassword = secrets.token_urlsafe(24)

def php(code, *args):
    return subprocess.check_output(['php', '-r', code, *args], text=True).strip()
php('require "php/models/User.php"; $db=require "php/db.php"; (new User($db))->create("Auth Test",$argv[1],$argv[2]);', email, password)

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
            with self.opener.open(req) as res:
                (code, body) = (res.status, res.read())
        except urllib.error.HTTPError as res:
            (code, body) = (res.code, res.read())
        result = json.loads(body)
        assert code == status, (path, code, result)
        if 'csrf' in result:
            self.token = result['csrf']
        return result
try:
    a = Client()
    b = Client()
    assert a.call('/auth/me')['user'] is None
    a.call('/contacts', status=401)
    a.call('/subscribers?export=csv', status=401)
    a.call('/auth/login', {'email': email, 'password': password}, status=419, csrf=False)
    for i in range(5):
        a.call('/auth/login', {'email': email, 'password': 'incorrect-password'}, status=401)
    a.call('/auth/login', {'email': email, 'password': password}, status=429)
    php('$db=require "php/db.php"; $s=$db->prepare("DELETE FROM rate_limits WHERE identifier=?");$s->execute([hash("sha256","login-email:".$argv[1])]);', email)
    assert a.call('/auth/login', {'email': email, 'password': password})['user']['email'] == email
    a.call('/contacts')
    a.call('/subscribers')
    a.call('/auth/profile', {'name': 'Updated Test'}, 'PATCH', 419, False)
    assert a.call('/auth/profile', {'name': 'Updated Test'}, 'PATCH')['user']['name'] == 'Updated Test'
    b.call('/auth/me')
    b.call('/auth/login', {'email': email, 'password': password})
    a.call('/auth/password', {'current_password': 'wrong', 'password': newpassword, 'password_confirmation': newpassword}, status=422)
    a.call('/auth/password', {'current_password': password, 'password': newpassword, 'password_confirmation': newpassword})
    assert b.call('/auth/me')['user'] is None
    token = php('require "php/models/PasswordReset.php"; $db=require "php/db.php"; $u=(new User($db))->byEmail($argv[1]); echo (new PasswordReset($db))->issue((int)$u["id"]);', email)
    b.call('/auth/reset-password', {'token': token, 'password': password, 'password_confirmation': password})
    assert a.call('/auth/me')['user'] is None
    b.call('/auth/me')
    b.call('/auth/reset-password', {'token': token, 'password': password, 'password_confirmation': password}, status=422)
    b.call('/auth/login', {'email': email, 'password': password})
    b.call('/auth/logout', {})
    b.call('/contacts', status=401)
    print('PASS: session bootstrap, CSRF rejection, protected reads/exports, login rate limiting, profile update, password change, other-session invalidation, single-use reset, logout.')
finally:
    php('$db=require "php/db.php";$u=$db->prepare("SELECT id FROM users WHERE email=?");$u->execute([$argv[1]]);$id=$u->fetchColumn();$s=$db->prepare("DELETE FROM password_resets WHERE user_id=?");$s->execute([$id]);$s=$db->prepare("DELETE FROM users WHERE email=?");$s->execute([$argv[1]]);$s=$db->prepare("DELETE FROM rate_limits WHERE identifier IN (?, ?, ?)");$s->execute([hash("sha256","login-email:".$argv[1]),hash("sha256","forgot-email:".$argv[1]),hash("sha256","change-password:".$id)]);', email)
    print('Disposable authentication account removed.')
