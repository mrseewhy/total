import os
from pathlib import Path
if Path(os.environ.get('TOTAL_INTEGRATION_FIXTURE','/')).resolve() != Path.cwd().resolve() or not (Path.cwd() / '.integration-fixture').is_file():
    raise SystemExit('Run through npm run test:integration -- --local. This case requires an isolated fixture.')
import json, uuid, secrets, subprocess, urllib.request, urllib.error, http.cookiejar, base64
base = os.environ['TOTAL_TEST_BASE']
tag = 'metadata-check-' + uuid.uuid4().hex
password = secrets.token_urlsafe(24)

def php(code, *args):
    return subprocess.check_output(['php', '-r', code, *args], text=True).strip()
backup = json.loads(php('$db=require "php/db.php";echo json_encode(["settings"=>$db->query("SELECT * FROM seo_settings")->fetchAll(),"pages"=>$db->query("SELECT * FROM page_metadata")->fetchAll()]);'))
email = tag + '@example.com'
manager = tag + '-manager@example.com'
php('require "php/models/User.php";$db=require "php/db.php";(new User($db))->create($argv[1],$argv[2],$argv[3]);', tag, email, password)

class Client:

    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token = ''

    def call(self, path, data=None, method=None, status=200, csrf=True, raw=False):
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
    anon = Client()
    anon.call('/metadata', status=401)
    anon.call('/activity', status=401)
    a = Client()
    a.call('/auth/me')
    a.call('/auth/login', {'email': email, 'password': password})
    original = a.call('/seo?path=/')
    form = a.call('/metadata')
    settings = form['settings']
    a.call('/metadata', {'scope': 'page', 'path': '/', 'title': 'x', 'description': '', 'image': '', 'noindex': False}, 'PUT', 419, False)
    a.call('/metadata', {'scope': 'page', 'path': '/not-registered', 'noindex': False}, 'PUT', 422)
    a.call('/metadata', {'scope': 'page', 'path': '/', 'title': 'x' * 71, 'noindex': False}, 'PUT', 422)
    a.call('/metadata', {'scope': 'page', 'path': '/', 'image': 'javascript:alert(1)', 'noindex': False}, 'PUT', 422)
    a.call('/metadata', {'scope': 'page', 'path': '/', 'title': 'Search $99 & homes', 'description': 'A custom home search description.', 'image': 'https://example.com/share.jpg', 'noindex': False}, 'PUT')
    page = a.call('/seo?path=/')

    def payload(r):
        return r.get('data', r)
    page = payload(page)
    before = payload(original)
    assert page['seo']['title'] == 'Search $99 & homes'
    assert page['seo']['description'] == 'A custom home search description.'
    assert page['heading'] == before['heading'] and page['intro'] == before['intro']
    a.call('/metadata', {'scope': 'settings', **settings, 'site_name': 'Metadata Test Brand', 'noindex': False}, 'PUT')
    assert payload(a.call('/seo?path=/about'))['seo']['siteName'] == 'Metadata Test Brand'
    a.call('/metadata', {'scope': 'page', 'path': '/about', 'title': '', 'description': '', 'image': '', 'noindex': True}, 'PUT')
    result = php('putenv("SITE_URL=https://metadata.example");require "php/seo/PageData.php";$p=new PageData();echo json_encode([$p->resolve("/about")["seo"]["robots"],$p->resolve("/contact")["seo"]["robots"]]);')
    assert json.loads(result)[0].startswith('noindex') and json.loads(result)[1].startswith('index')
    xml = php('putenv("SITE_URL=https://metadata.example");require "php/seo/sitemap.php";sitemapResponse("/sitemap-pages.xml");')
    assert 'https://metadata.example/about' not in xml and 'https://metadata.example/contact' in xml
    a.call('/metadata', {'scope': 'settings', **settings, 'noindex': True}, 'PUT')
    assert json.loads(php('putenv("SITE_URL=https://metadata.example");require "php/seo/PageData.php";echo json_encode((new PageData())->resolve("/contact")["seo"]["robots"]);')).startswith('noindex')
    a.call('/metadata', {'scope': 'settings', **settings, 'noindex': False}, 'PUT')
    a.call('/metadata', {'scope': 'page', 'path': '/', 'title': '', 'description': '', 'image': '', 'noindex': False}, 'PUT')
    assert payload(a.call('/seo?path=/'))['seo']['title'] == before['seo']['title']
    user = {'name': tag + ' Manager', 'email': manager, 'role': 'Manager', 'password': password, 'password_confirmation': password}
    a.call('/users', user, 'POST', 201)
    uid = next((x['id'] for x in a.call('/users')['data'] if x['email'] == manager))
    m = Client()
    m.call('/auth/me')
    m.call('/auth/login', {'email': manager, 'password': password})
    m.call('/metadata')
    m.call('/metadata', {'scope': 'page', 'path': '/contact', 'title': 'Contact metadata check', 'description': '', 'image': '', 'noindex': False}, 'PUT')
    m.call('/users', status=403)
    assert m.call('/activity?module=users')['data'] == []
    content = {'title': tag, 'slug': tag, 'excerpt': 'A disposable test article.', 'content': [{'type': 'paragraph', 'content': [{'type': 'text', 'text': 'Test body', 'styles': {}}]}], 'status': 'draft', 'details': {}, 'featured_media_id': None, 'seo_noindex': False}
    post = m.call('/posts', content, 'POST', 201)['data']
    postid = post['id']
    m.call('/posts/' + str(postid), {**content, 'status': 'published'}, 'PATCH')
    m.call('/posts/' + str(postid), method='DELETE')
    boundary = '----' + uuid.uuid4().hex
    image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aA1sAAAAASUVORK5CYII=')
    body = ('--' + boundary + '\r\nContent-Disposition: form-data; name="file"; filename="activity-check.png"\r\nContent-Type: image/png\r\n\r\n').encode() + image + ('\r\n--' + boundary + '--\r\n').encode()
    req = urllib.request.Request(base + '/media', data=body, headers={'Content-Type': 'multipart/form-data; boundary=' + boundary, 'X-CSRF-Token': m.token}, method='POST')
    with m.opener.open(req) as r:
        media = json.loads(r.read())['data']
    m.call('/media/' + str(media['id']), {'title': 'Activity test image', 'alt_text': 'A test pixel', 'caption': ''}, 'PATCH')
    m.call('/media/' + str(media['id']), method='DELETE')
    contactid = php('require "php/models/Contact.php";$db=require "php/db.php";(new Contact($db))->create(["name"=>$argv[1],"email"=>"test@example.com","phone"=>"","interest"=>"General inquiry","message"=>"Disposable activity test inquiry"]);$q=$db->prepare("SELECT id FROM contacts WHERE name=?");$q->execute([$argv[1]]);echo $q->fetchColumn();', tag)
    m.call('/contacts/' + contactid + '/read', {'is_read': True}, 'PATCH')
    m.call('/contacts/' + contactid + '/read', {'is_read': False}, 'PATCH')
    m.call('/auth/profile', {'name': tag + ' Renamed'}, 'PATCH')
    m.call('/auth/password', {'current_password': password, 'password': password + 'new', 'password_confirmation': password + 'new'}, 'POST')
    m.call('/auth/logout', {}, 'POST')
    token = php('require "php/models/PasswordReset.php";$db=require "php/db.php";echo (new PasswordReset($db))->issue((int)$argv[1]);', str(uid))
    a.call('/auth/reset-password', {'token': token, 'password': password + 'reset', 'password_confirmation': password + 'reset'}, 'POST')
    a.call('/auth/me')
    a.call('/auth/login', {'email': email, 'password': password})
    a.call('/users/' + str(uid) + '/status', {'is_active': False}, 'PATCH')
    a.call('/users/' + str(uid) + '/status', {'is_active': True}, 'PATCH')
    a.call('/users/' + str(uid), method='DELETE')
    php('require "php/functions/activity.php";$db=require "php/db.php";$q=$db->prepare("SELECT * FROM users WHERE email=?");$q->execute([$argv[1]]);$u=$q->fetch();for($i=0;$i<25;$i++)recordActivity($db,$u,"seo","test",$i,"Pagination verification ".$i);', email)
    feed = a.call('/activity?module=seo')
    assert len(feed['data']) == 20 and feed['pagination']['pages'] >= 2
    assert a.call('/activity?module=seo&page=2')['data']
    entries = json.loads(php('$db=require "php/db.php";$q=$db->prepare("SELECT module,action,summary FROM activity_log WHERE actor_name LIKE ? ORDER BY id");$q->execute([$argv[1]."%"]);echo json_encode($q->fetchAll());', tag))
    actions = {(e['module'], e['action']) for e in entries}
    for pair in [('posts', 'created'), ('posts', 'updated'), ('posts', 'deleted'), ('users', 'create'), ('users', 'ban'), ('users', 'unban'), ('users', 'delete'), ('auth', 'signed_in'), ('auth', 'signed_out'), ('profile', 'updated'), ('profile', 'password_changed'), ('seo', 'updated'), ('media', 'uploaded'), ('media', 'updated'), ('media', 'deleted'), ('contacts', 'marked_read'), ('contacts', 'marked_unread'), ('auth', 'password_reset')]:
        assert pair in actions, pair
    assert password not in json.dumps(entries) and email not in json.dumps(entries)
    print('PASS: permissions, CSRF, invalid metadata rejection, save/reset fallbacks, visible content unchanged, schema defaults, noindex and sitemap exclusions, Manager access, activity action coverage, pagination/filtering, and no credentials in activity.')
finally:
    php('$db=require "php/db.php";$backup=json_decode($argv[2],true);$q=$db->prepare("DELETE FROM contacts WHERE name=?");$q->execute([$argv[1]]);foreach(["seo_settings"=>"settings","page_metadata"=>"pages"]as $table=>$key){$db->exec("DELETE FROM $table");foreach($backup[$key]as $row){$cols=implode(",",array_keys($row));$vals=implode(",",array_fill(0,count($row),"?"));$q=$db->prepare("INSERT INTO $table ($cols) VALUES ($vals)");$q->execute(array_values($row));}}$q=$db->prepare("DELETE FROM activity_log WHERE actor_name LIKE ?");$q->execute([$argv[1]."%"]);$q=$db->prepare("SELECT id FROM users WHERE email LIKE ?");$q->execute([$argv[1]."%@example.com"]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);foreach($ids as $id){foreach(["posts","events","properties"]as $t){$s=$db->prepare("SELECT id,slug FROM $t WHERE author_id=?");$s->execute([$id]);foreach($s->fetchAll()as $r){$d=$db->prepare("DELETE FROM content_media WHERE content_kind=? AND content_id=?");$d->execute([$t,$r["id"]]);$d=$db->prepare("DELETE FROM seo_redirects WHERE kind=? AND new_slug=?");$d->execute([$t,$r["slug"]]);}$s=$db->prepare("DELETE FROM $t WHERE author_id=?");$s->execute([$id]);}$s=$db->prepare("DELETE FROM password_resets WHERE user_id=?");$s->execute([$id]);}$q=$db->prepare("DELETE FROM users WHERE email LIKE ?");$q->execute([$argv[1]."%@example.com"]);', tag, json.dumps(backup))
    print('Restored metadata and removed disposable test accounts, content, and activity.')
