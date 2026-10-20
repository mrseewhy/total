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
    anon.call('/services', status=401)
    public = anon.call('/seo?path=/services')
    assert public['type'] == 'listing' and len(public['items']) == 6
    for item in public['items']:
        detail = anon.call('/seo?path=' + item['url'])
        assert detail['html'] and detail['kind'] == 'services'
        assert any((x.get('mainEntity', {}).get('@type') == 'Service' for x in detail['seo']['schema']['@graph']))
    m = Client()
    m.call('/auth/me')
    m.call('/auth/login', {'email': email, 'password': password})
    m.call('/users', status=403)
    data = {'title': tag, 'slug': tag, 'excerpt': 'Service test excerpt', 'content': [{'type': 'paragraph', 'content': [{'type': 'text', 'text': 'Service test body', 'styles': {}}]}], 'status': 'draft', 'details': {}, 'featured_media_id': None, 'seo_noindex': False}
    m.call('/services', data, 'POST', 419, False)
    m.call('/services', {**data, 'title': ''}, 'POST', 422)
    m.call('/services', {**data, 'status': 'scheduled', 'publish_at': '2027-01-01T10:00:00Z'}, 'POST', 422)
    item = m.call('/services', data, 'POST', 201)['data']
    id = item['id']
    m.call('/services', data, 'POST', 409)
    m.call('/public/services/' + tag, status=404)
    anon.call('/seo?path=/services/' + tag, status=404)
    boundary = '----' + uuid.uuid4().hex
    image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aA1sAAAAASUVORK5CYII=')
    body = ('--' + boundary + '\r\nContent-Disposition: form-data; name="file"; filename="service-check.png"\r\nContent-Type: image/png\r\n\r\n').encode() + image + ('\r\n--' + boundary + '--\r\n').encode()
    req = urllib.request.Request(base + '/media', data=body, headers={'Content-Type': 'multipart/form-data; boundary=' + boundary, 'X-CSRF-Token': m.token}, method='POST')
    with m.op.open(req) as r:
        media = json.loads(r.read())['data']
    published = {**data, 'status': 'published', 'featured_media_id': media['id'], 'seo_title': 'Service search title', 'seo_description': 'Service search description'}
    m.call('/services/' + str(id), published, 'PATCH')
    m.call('/media/' + str(media['id']), method='DELETE', status=422)
    detail = anon.call('/seo?path=/services/' + tag)
    assert detail['seo']['title'] == 'Service search title' and detail['image']
    xml = php('putenv("SITE_URL=https://services.example");require "php/seo/sitemap.php";sitemapResponse("/sitemap-services-1.xml");')
    assert tag in xml
    m.call('/services/' + str(id), {**published, 'seo_noindex': True}, 'PATCH')
    xml = php('putenv("SITE_URL=https://services.example");require "php/seo/sitemap.php";sitemapResponse("/sitemap-services-1.xml");')
    assert tag not in xml
    renamed = {**published, 'slug': tag + '-renamed'}
    m.call('/services/' + str(id), renamed, 'PATCH')
    assert anon.call('/seo?path=/services/' + tag)['redirect'] == '/services/' + tag + '-renamed'
    listed = m.call('/services?search=' + tag)
    assert len(listed['data']) == 1 and listed['pagination']['perPage'] == 20
    other = m.call('/services', {**data, 'slug': tag + '-second'}, 'POST', 201)['data']['id']
    m.call('/services/bulk-delete', {'ids': [id, other]}, 'POST')
    anon.call('/seo?path=/services/' + tag + '-renamed', status=404)
    m.call('/media/' + str(media['id']), method='DELETE')
    feed = m.call('/activity?module=services')['data']
    assert {'created', 'updated', 'deleted'} <= set((x['action'] for x in feed if x['actor_name'] == tag))
    assert len(anon.call('/seo?path=/services')['items']) == 6
    print('PASS: six public services/detail schemas, Manager CRUD, validation/CSRF/permissions, drafts, duplicate slugs, media usage protection, SEO/sitemap/noindex, search/pagination, redirects, bulk delete, and activity logging.')
finally:
    php('$db=require "php/db.php";$q=$db->prepare("SELECT id FROM users WHERE email=?");$q->execute([$argv[1]]);$uid=$q->fetchColumn();if($uid){$q=$db->prepare("SELECT id,slug FROM services WHERE author_id=?");$q->execute([$uid]);foreach($q->fetchAll()as $r){$s=$db->prepare("DELETE FROM content_media WHERE content_kind=? AND content_id=?");$s->execute(["services",$r["id"]]);$s=$db->prepare("DELETE FROM seo_redirects WHERE kind=? AND new_slug=?");$s->execute(["services",$r["slug"]]);}$s=$db->prepare("DELETE FROM services WHERE author_id=?");$s->execute([$uid]);require "php/models/Media.php";$s=$db->prepare("SELECT id,filename FROM media WHERE uploaded_by=?");$s->execute([$uid]);foreach($s->fetchAll()as $r){$file=Media::directory()."/".$r["filename"];if(is_file($file))unlink($file);$d=$db->prepare("DELETE FROM media WHERE id=?");$d->execute([$r["id"]]);}$s=$db->prepare("DELETE FROM activity_log WHERE actor_id=?");$s->execute([$uid]);$s=$db->prepare("DELETE FROM users WHERE id=?");$s->execute([$uid]);}', email)
    print('Disposable service test fixtures removed; initial six services retained.')
