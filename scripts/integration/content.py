import os
from pathlib import Path
if Path(os.environ.get('TOTAL_INTEGRATION_FIXTURE','/')).resolve() != Path.cwd().resolve() or not (Path.cwd() / '.integration-fixture').is_file():
    raise SystemExit('Run through npm run test:integration -- --local. This case requires an isolated fixture.')
import urllib.request, urllib.error, http.cookiejar, json, uuid, secrets, subprocess, base64, datetime
base = os.environ['TOTAL_TEST_BASE']
tag = 'cms-check-' + uuid.uuid4().hex
email = tag + '@example.com'
password = secrets.token_urlsafe(24)

def php(code, *args):
    return subprocess.check_output(['php', '-r', code, *args], text=True).strip()
php('require "php/models/User.php";$db=require "php/db.php";(new User($db))->create("CMS Test",$argv[1],$argv[2]);$s=$db->prepare("UPDATE users SET role=? WHERE email=?");$s->execute(["Manager",$argv[1]]);', email, password)
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
token = ''
created = []
media = []

def call(path, data=None, method=None, status=200, raw=False, headers=None):
    global token
    h = {'Content-Type': 'application/json', 'X-CSRF-Token': token}
    h.update(headers or {})
    req = urllib.request.Request(base + path, data=data if raw else json.dumps(data).encode() if data is not None else None, headers=h, method=method)
    try:
        with opener.open(req) as res:
            (code, body) = (res.status, res.read())
    except urllib.error.HTTPError as res:
        (code, body) = (res.code, res.read())
    try:
        result = json.loads(body)
    except:
        raise AssertionError((path, code, body[:400]))
    assert code == status, (path, code, result)
    if result.get('csrf'):
        token = result['csrf']
    return result
try:
    call('/posts', status=401)
    call('/auth/me')
    call('/auth/login', {'email': email, 'password': password})
    call('/users', status=403)
    call('/auth/profile', {'name': 'CMS Test', 'email': 'different@example.com'}, 'PATCH', 422)
    boundary = 'test-' + uuid.uuid4().hex

    def upload(image, name='test.png', mime='image/png', expected=201):
        body = f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + image + f'\r\n--{boundary}--\r\n'.encode()
        return call('/media', body, 'POST', expected, True, {'Content-Type': 'multipart/form-data; boundary=' + boundary})
    upload(b'<?php echo "bad"; ?>', 'fake.png', 'image/png', 422)
    image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jC1kAAAAASUVORK5CYII=')
    m = upload(image)['data']
    media.append(m['id'])
    assert urllib.request.urlopen(os.environ['TOTAL_TEST_ORIGIN'] + m['url']).status == 200
    call('/media/' + str(m['id']), {'title': 'Test image', 'alt_text': 'Test alt', 'caption': 'Caption'}, 'PATCH')
    blocks = [{'id': 'block-test', 'type': 'paragraph', 'props': {}, 'content': [{'type': 'text', 'text': 'Content test', 'styles': {}}], 'children': []}, {'id': 'image-test', 'type': 'image', 'props': {'url': m['url'], 'caption': 'An image'}, 'children': []}]
    payload = {'title': 'Test post', 'slug': tag, 'excerpt': 'Excerpt', 'content': blocks, 'details': {}, 'status': 'draft', 'featured_media_id': m['id'], 'publish_at': None}
    p = call('/posts', payload, 'POST', 201)['data']
    created.append(('posts', p['id']))
    call('/public/posts/' + tag, status=404)
    call('/media/' + str(m['id']), method='DELETE', status=422)
    call('/posts', payload, 'POST', 409)
    future = (datetime.datetime.now(datetime.timezone.utc) + datetime.timedelta(hours=1)).isoformat(timespec='seconds').replace('+00:00', 'Z')
    scheduled = call('/posts/' + str(p['id']), {**payload, 'status': 'scheduled', 'publish_at': future}, 'PATCH')['data']
    assert scheduled['effective_status'] == 'scheduled'
    call('/public/posts/' + tag, status=404)
    php('$db=require "php/db.php";$s=$db->prepare("UPDATE posts SET publish_at=? WHERE slug=?");$s->execute([gmdate("Y-m-d H:i:s",time()-60),$argv[1]]);', tag)
    assert call('/public/posts/' + tag)['data']['effective_status'] == 'published'
    assert call('/posts?status=published')['pagination']['total'] >= 1
    for (kind, details) in [('events', {'location': 'Lagos', 'starts_at': future, 'ends_at': None}), ('properties', {'location': 'Ikoyi', 'price': '1200000.50', 'currency': 'NGN', 'listing_type': 'sale', 'availability': 'available', 'bedrooms': 3, 'bathrooms': 2, 'gallery_ids': [m['id']]})]:
        row = call('/' + kind, {**payload, 'slug': tag + '-' + kind, 'details': details, 'status': 'published'}, 'POST', 201)['data']
        created.append((kind, row['id']))
        assert call('/public/' + kind + '/' + tag + '-' + kind)['data']['title'] == 'Test post'
        call('/' + kind + '/' + str(row['id']))
    call('/events', {**payload, 'slug': tag + '-invalid', 'status': 'published', 'details': {'location': 'Lagos', 'starts_at': future, 'ends_at': '2020-01-01T00:00:00Z'}}, 'POST', 422)
    call('/properties', {**payload, 'details': {'price': '-2'}}, 'POST', 422)
    call('/posts', {**payload, 'content': [{'type': 'image', 'props': {'url': 'javascript:alert(1)'}}]}, 'POST', 422)
    for (kind, id) in created:
        call('/' + kind + '/bulk-delete', {'ids': [id]}, 'POST')
    created = []
    call('/media/bulk-delete', {'ids': media}, 'POST')
    media = []
    print('PASS: Manager CMS access and denied user management; immutable email; image upload/type validation; image URL serving; metadata; drafts; slug uniqueness; scheduling before/after due time; event/property CRUD; validation; media-use protection; bulk deletion.')
finally:
    php('$db=require "php/db.php";$q=$db->prepare("SELECT id FROM users WHERE email=?");$q->execute([$argv[1]]);$id=$q->fetchColumn();foreach(["posts","events","properties"] as $table){$s=$db->prepare("SELECT id FROM $table WHERE author_id=?");$s->execute([$id]);foreach($s->fetchAll(PDO::FETCH_COLUMN) as $cid){$d=$db->prepare("DELETE FROM content_media WHERE content_kind=? AND content_id=?");$d->execute([$table,$cid]);}$d=$db->prepare("DELETE FROM $table WHERE author_id=?");$d->execute([$id]);}$s=$db->prepare("SELECT filename FROM media WHERE uploaded_by=?");$s->execute([$id]);foreach($s->fetchAll(PDO::FETCH_COLUMN) as $filename)@unlink("public/uploads/".$filename);$s=$db->prepare("DELETE FROM media WHERE uploaded_by=?");$s->execute([$id]);$s=$db->prepare("DELETE FROM users WHERE id=?");$s->execute([$id]);', email)
    print('Disposable CMS records, files, and account removed.')
