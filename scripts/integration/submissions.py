from common import Client,php
import csv
import io
import json
import urllib.request
from common import BASE

user='submissions@example.com'
password='submission-fixture-password-123'
php('require "php/models/User.php";$db=require "php/db.php";(new User($db))->create("Submissions Fixture",$argv[1],$argv[2]);',user,password)
anon=Client()
anon.call('/subscribers',{'email':'bad','website':''},'POST',422)
anon.call('/contacts',{'website':'bot'},'POST',422)
php('require "php/models/Contact.php";$db=require "php/db.php";$q=$db->prepare("INSERT INTO subscribers(email,created_at)VALUES(?,?)");for($i=0;$i<41;$i++){$q->execute(["subscriber".$i."@example.com",gmdate("Y-m-d H:i:s")]);(new Contact($db))->create(["name"=>"Inquiry ".$i,"email"=>"contact".$i."@example.com","phone"=>"","interest"=>"General inquiry","message"=>"Disposable inquiry for pagination."]);}')
a=Client();a.call('/auth/me');a.call('/auth/login',{'email':user,'password':password})
for path in ['/subscribers','/contacts']:
    seen=[]
    for page in range(1,4):
        data=a.call(path+'?page='+str(page));assert len(data['data'])<=20 and data['pagination']['total']==41
        seen.extend(row['id'] for row in data['data'])
        if path=='/contacts':assert all(not row['is_read'] for row in data['data'])
    assert len(seen)==len(set(seen))==41
    a.call(path+'?page=0',status=422)
with a.op.open(BASE+'/subscribers?export=csv') as response:
    rows=list(csv.reader(io.StringIO(response.read().decode('utf-8-sig'))))
assert len(rows)==42
inquiry=a.call('/contacts')['data'][0]
a.call('/contacts/'+str(inquiry['id'])+'/read',{'is_read':True},'PATCH',419,False)
assert a.call('/contacts/'+str(inquiry['id'])+'/read',{'is_read':True},'PATCH')['data']['is_read'] is True
assert a.call('/dashboard')['counts']['unread']==40
assert a.call('/contacts/'+str(inquiry['id'])+'/read',{'is_read':False},'PATCH')['data']['is_read'] is False
print('PASS: protected real-data pagination/CSV, field validation, honeypot, initial unread status and read/unread dashboard updates.')
