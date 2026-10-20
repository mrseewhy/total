"""Local security integration tests. Requires the local API/Vite servers and a disposable development database; never run with production credentials. Test records are prefixed and removed; localhost counters are restored."""
import sys
import os
import json, uuid, secrets, subprocess, urllib.request, urllib.error, http.cookiejar, tempfile, shutil
from pathlib import Path

def main():
    if sys.argv[1:] != ['--local']:
        raise SystemExit('Run from the project root: python3 scripts/security-e2e.py --local. Use an isolated local development database only.')
    base = os.environ.get('TOTAL_TEST_BASE','http://127.0.0.1:5173/api')
    tag = 'security-' + uuid.uuid4().hex
    email = tag + '@example.com'
    password = secrets.token_urlsafe(24)
    uid = None

    def php(code, *args):
        return subprocess.check_output(['php', '-r', code, *args], text=True).strip()
    actions = ['newsletter', 'contact', 'login-ip', 'forgot-ip', 'reset-ip']
    keys = json.loads(php('$keys=[];foreach(["127.0.0.1","::1"]as $ip)foreach(json_decode($argv[1],true)as $a)$keys[]=hash("sha256",$a.":".$ip);echo json_encode($keys);', json.dumps(actions)))
    backup = php('$db=require "php/db.php";$keys=json_decode($argv[1],true);$sql="SELECT * FROM rate_limits WHERE identifier IN (".implode(",",array_fill(0,count($keys),"?")).")";$q=$db->prepare($sql);$q->execute($keys);echo json_encode($q->fetchAll());$q=$db->prepare("DELETE FROM rate_limits WHERE identifier IN (".implode(",",array_fill(0,count($keys),"?")).")");$q->execute($keys);', json.dumps(keys))

    class Client:

        def __init__(self):
            self.jar = http.cookiejar.CookieJar()
            self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
            self.token = ''

        def call(self, path, data=None, method=None, status=200, csrf=True, content_type='application/json', extra=None, raw=None):
            h = {'Content-Type': content_type, **(extra or {})}
            if csrf:
                h['X-CSRF-Token'] = self.token
            body = raw if raw is not None else json.dumps(data).encode() if data is not None else None
            req = urllib.request.Request(base + path, data=body, headers=h, method=method)
            try:
                with self.op.open(req) as r:
                    (code, headers, body) = (r.status, r.headers, r.read())
            except urllib.error.HTTPError as r:
                (code, headers, body) = (r.code, r.headers, r.read())
            result = json.loads(body)
            assert code == status, (path, code, result)
            assert headers['X-Content-Type-Options'] == 'nosniff'
            assert headers['Referrer-Policy'] == 'no-referrer'
            assert headers['X-Frame-Options'] == 'DENY'
            assert 'no-store' in headers['Cache-Control']
            if result.get('csrf'):
                self.token = result['csrf']
            return result
    try:
        a = Client()
        anon = Client()
        for path in ['/users', '/contacts', '/subscribers?export=csv', '/media', '/activity', '/metadata', '/posts', '/services', '/events', '/properties']:
            anon.call(path, status=401)
        anon.call('/contacts', {'name': 'x'}, 'POST', 415, content_type='text/plain')
        anon.call('/subscribers', {'email': email}, 'POST', 415, content_type='application/x-www-form-urlencoded')
        anon.call('/subscribers', method='POST', raw=b'{"email":"' + b'x' * 1100000 + b'"}', status=413)
        anon.call('/subscribers', method='POST', raw=b'{broken', status=422)
        anon.call('/subscribers', method='POST', raw=b'[]', status=422)
        anon.call('/subscribers', method='POST', raw=('{' + '"x":' * 0 + '"x":' + '[' * 70 + '0' + ']' * 70 + '}').encode(), status=422)
        for honey in ['bot', [], None]:
            anon.call('/subscribers', {'email': email, 'website': honey}, 'POST', 422)
        for i in range(3):
            anon.call('/subscribers', {'email': tag + str(i) + '@example.com', 'website': ''}, 'POST', 201)
        anon.call('/subscribers', {'email': tag + 'blocked@example.com', 'website': ''}, 'POST', 429, extra={'X-Forwarded-For': '198.51.100.10'})
        inquiry = {'name': tag, 'email': email, 'phone': '', 'interest': 'General inquiry', 'message': 'A disposable security-check inquiry.', 'website': '', 'is_read': True}
        for i in range(3):
            anon.call('/contacts', inquiry, 'POST', 201)
        anon.call('/contacts', inquiry, 'POST', 429, extra={'X-Forwarded-For': '198.51.100.11'})
        assert php('$db=require "php/db.php";$q=$db->prepare("SELECT MAX(is_read) FROM contacts WHERE email=?");$q->execute([$argv[1]]);echo $q->fetchColumn();', email) == '0'
        uid = int(php('require "php/models/User.php";$db=require "php/db.php";$m=new User($db);$m->create($argv[1],$argv[2],$argv[3]);$u=$m->byEmail($argv[2]);$q=$db->prepare("UPDATE users SET role=? WHERE id=?");$q->execute(["Manager",$u["id"]]);echo $u["id"];', tag, email, password))
        a.call('/auth/me')
        before = next((c.value for c in a.jar if c.name == 'total_session'))
        a.call('/auth/login', {'email': email, 'password': password}, 'POST', 419, False)
        a.call('/auth/login', {'email': email, 'password': password}, 'POST')
        after = next((c.value for c in a.jar if c.name == 'total_session'))
        assert before != after
        a.call('/users', status=403)
        a.call('/posts', {'title': 'x'}, 'POST', 419, False)
        content = {'title': 'Security rich-text test', 'slug': tag, 'excerpt': 'Escaped content test', 'content': [{'type': 'paragraph', 'content': [{'type': 'text', 'text': '<script>alert(1)</script>', 'styles': {}}]}], 'status': 'published', 'details': {}, 'featured_media_id': None, 'seo_noindex': False}
        post = a.call('/posts', content, 'POST', 201)['data']
        page = anon.call('/seo?path=/blog/' + tag)
        assert '&lt;script&gt;' in page['html'] and '<script>' not in page['html']
        unsafe = {**content, 'slug': tag + '-unsafe', 'content': [{'type': 'paragraph', 'content': [{'type': 'link', 'href': 'javascript:alert(1)', 'content': []}]}]}
        a.call('/posts', unsafe, 'POST', 422)
        a.call('/posts?search=%27%20OR%201%3D1--')
        a.call('/posts/' + str(post['id']), method='DELETE')
        stale = php('require "php/models/User.php";require "php/models/PasswordReset.php";$db=require "php/db.php";$m=new User($db);$u=$m->byEmail($argv[1]);$r=new PasswordReset($db);$t1=$r->issue((int)$u["id"]);$t2=$r->issue((int)$u["id"]);if($r->consume($t1,$argv[2]."old"))throw new Exception("Old token accepted");if(!$r->consume($t2,$argv[2]."reset"))throw new Exception("Reset failed");if($r->consume($t2,$argv[2]."again"))throw new Exception("Token replay");if($m->changeOwnPassword((int)$u["id"],$argv[2],$argv[2]."stale",(int)$u["session_version"]))throw new Exception("Stale password change accepted");if(!password_verify($argv[2]."reset",$m->byId((int)$u["id"])["password_hash"]))throw new Exception("Reset overwritten");echo "stale request rejected";', email, password)
        assert stale == 'stale request rejected'
        a.call('/contacts', status=401)
        proof = php('require "php/controllers/AuthController.php";$db=require "php/db.php";$_SERVER["REMOTE_ADDR"]="192.0.2.77";$action=$argv[1];checkRateLimit($db,"192.0.2.77",$action."-ip",1,600);$method=new ReflectionMethod(AuthController::class,"limited");ob_start();$blocked=$method->invoke(new AuthController(),$db,$argv[2],$action,3,1,600);ob_end_clean();$q=$db->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier=?");$q->execute([hash("sha256",$action."-email:".$argv[2])]);$n=(int)$q->fetchColumn();$q=$db->prepare("DELETE FROM rate_limits WHERE identifier IN (?,?)");$q->execute([hash("sha256",$action."-ip:192.0.2.77"),hash("sha256",$action."-email:".$argv[2])]);if(!$blocked||$n!==0)throw new Exception("Blocked IP allocated account state");echo "blocked IP short-circuits";', tag, email)
        assert proof == 'blocked IP short-circuits'
        with tempfile.TemporaryDirectory(prefix='total-security-bootstrap-') as folder:
            fixture = Path(folder)
            shutil.copytree('php', fixture / 'php', ignore=shutil.ignore_patterns('config.php', '*.config.local.php', 'storage'))
            (fixture / 'public/api').mkdir(parents=True)
            shutil.copy('public/api/index.php', fixture / 'public/api/index.php')
            (fixture / 'php/config/content-modules.json').write_text('{broken')
            result = subprocess.run(['php', '-r', '$_SERVER["REQUEST_URI"]="/api/users";$_SERVER["REQUEST_METHOD"]="GET";require $argv[1];', str(fixture / 'public/api/index.php')], capture_output=True, text=True)
            response = json.loads(result.stdout)
            assert response['success'] is False and str(fixture) not in result.stdout and ('Stack trace' not in result.stdout)
        print('PASS: protected endpoints/Manager permissions, CSRF, session rotation/revocation, JSON type/size/depth limits, honeypots, public form persistence/unread/rate limits and forwarded-IP spoof rejection, stored XSS/link rejection, SQL search, reset replay/stale credentials, blocked-IP storage bound, generic bootstrap errors, and API security headers.')
    finally:
        php('$db=require "php/db.php";$keys=json_decode($argv[2],true);$q=$db->prepare("DELETE FROM rate_limits WHERE identifier IN (".implode(",",array_fill(0,count($keys),"?")).")");$q->execute($keys);foreach(json_decode($argv[3],true)as $r){$q=$db->prepare("INSERT INTO rate_limits (identifier,attempts,window_start) VALUES (?,?,?)");$q->execute([$r["identifier"],$r["attempts"],$r["window_start"]]);}$prefix=$argv[1];foreach(["contacts","subscribers"]as $table){$q=$db->prepare("DELETE FROM $table WHERE email LIKE ?");$q->execute([$prefix."%@example.com"]);}if($argv[4]!==""){$uid=(int)$argv[4];foreach(["posts","events","properties","services"]as $table){$q=$db->prepare("SELECT id,slug FROM $table WHERE author_id=?");$q->execute([$uid]);foreach($q->fetchAll()as $r){$d=$db->prepare("DELETE FROM content_media WHERE content_kind=? AND content_id=?");$d->execute([$table,$r["id"]]);$d=$db->prepare("DELETE FROM seo_redirects WHERE kind=? AND new_slug=?");$d->execute([$table,$r["slug"]]);}$q=$db->prepare("DELETE FROM $table WHERE author_id=?");$q->execute([$uid]);}foreach(["activity_log"=>"actor_id","password_resets"=>"user_id","users"=>"id"]as $table=>$column){$q=$db->prepare("DELETE FROM $table WHERE $column=?");$q->execute([$uid]);}}', tag, json.dumps(keys), backup, str(uid or ''))
        print('Security fixtures removed and original localhost form/auth limits restored.')
if __name__ == '__main__':
    main()
