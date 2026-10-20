from common import Client,php
import email
from email import policy
from pathlib import Path
import json
import re
import subprocess

password='two-factor-fixture-password-123'
address='two-factor-manager@example.com'
plain='plain-manager@example.com'
php('require "php/models/User.php";$db=require "php/db.php";foreach([$argv[1],$argv[2]]as$email){(new User($db))->create("2FA Fixture",$email,$argv[3]);$q=$db->prepare("UPDATE users SET role=? WHERE email=?");$q->execute(["Manager",$email]);}',address,plain,password)


def limits():
    # This case runs in its own disposable database; do not reset any real site's limits.
    php('$db=require "php/db.php";$db->exec("DELETE FROM rate_limits");')


def enabled(value=True):
    Path('php/mail.config.local.php').write_text('<?php return '+('["enabled"=>true,"from_address"=>"noreply@example.com","from_name"=>"Haven"]' if value else '["enabled"=>false]')+';')


def code_for(recipient=address):
    messages=[]
    for file in Path('mail-capture').glob('*.eml'):
        message=email.message_from_bytes(file.read_bytes(),policy=policy.default)
        if str(message['To'])==recipient:
            found=re.search(r'Your verification code is:\s*(\d{6})',message.get_content())
            if found:messages.append((file.stat().st_mtime_ns,found.group(1),message))
    assert messages,'No captured local email for fixture account'
    return max(messages,key=lambda item:item[0])[1]


def sign_in(recipient=address,secret=password):
    c=Client();c.call('/auth/me');result=c.call('/auth/login',{'email':recipient,'password':secret})
    return c,result


def user_flag(recipient=address):
    return php('$db=require "php/db.php";$q=$db->prepare("SELECT two_factor_enabled FROM users WHERE email=?");$q->execute([$argv[1]]);echo (int)$q->fetchColumn();',recipient)=='1'


def setting(client,target,recipient=address):
    result=client.call('/auth/two-factor/request-setting',{'enabled':target,'current_password':password})
    assert 'code' not in result
    return code_for(recipient)


def start_login():
    client,result=sign_in()
    assert result['requires_two_factor'] is True and not result.get('user')
    assert client.call('/auth/me')['user'] is None
    return client,code_for()


a,result=sign_in()
assert result['user']['two_factor_enabled'] is False and not result.get('requires_two_factor')
a.call('/auth/two-factor/request-setting',{'enabled':True,'current_password':password},status=419,csrf=False)
a.call('/auth/two-factor/request-setting',{'enabled':True,'current_password':'wrong'},status=422)
assert not user_flag()
enabled(False)
a.call('/auth/two-factor/request-setting',{'enabled':True,'current_password':password},status=503)
assert not user_flag()
assert json.loads(php('$db=require "php/db.php";echo json_encode((int)$db->query("SELECT COUNT(*) FROM two_factor_challenges")->fetchColumn());'))==0
# Ordinary logins do not depend on mail being available.
assert sign_in(plain)[1]['user']['email']==plain
enabled();limits()
other_session,_=sign_in()
code=setting(a,True)
assert not user_flag()
assert 'code_hash' not in json.dumps(a.call('/auth/me'))
row=json.loads(php('$db=require "php/db.php";$q=$db->prepare("SELECT c.* FROM two_factor_challenges c JOIN users u ON c.user_id=u.id WHERE u.email=?");$q->execute([$argv[1]]);echo json_encode($q->fetch());',address))
assert row['code_hash']!=code and row['purpose']=='enable'
assert php('echo (int)password_verify($argv[1],$argv[2]);',code,row['code_hash'])=='1'
wrong='000000' if code!='000000' else '000001'
a.call('/auth/two-factor/verify-setting',{'code':wrong},status=422)
a.call('/auth/two-factor/verify-setting',{'code':code},status=419,csrf=False)
changed=a.call('/auth/two-factor/verify-setting',{'code':code})
assert changed['user']['two_factor_enabled'] is True and user_flag()
assert other_session.call('/auth/me')['user'] is None
# Confirming the setting cannot be replayed.
a.call('/auth/two-factor/verify-setting',{'code':code},status=422)
limits()
login,code=start_login()
for path in ['/posts','/subscribers','/contacts','/metadata']:
    login.call(path,status=401)
stranger=Client();stranger.call('/auth/me');stranger.call('/auth/two-factor/verify',{'code':code},status=422)
login.call('/auth/two-factor/verify',{'code':code},status=419,csrf=False)
assert login.call('/auth/two-factor/verify',{'code':code})['user']['email']==address
assert login.call('/auth/me')['user']['two_factor_enabled'] is True
login.call('/users',status=403)
login.call('/auth/two-factor/verify',{'code':code},status=422)
# A fresh browser/password step is required every time; resends replace older codes.
limits()
resend,old=start_login()
resend.call('/auth/two-factor/resend',{})
new=code_for();assert new!=old
resend.call('/auth/two-factor/verify',{'code':old},status=422)
assert resend.call('/auth/two-factor/verify',{'code':new})['user']
# Invalid codes are capped per challenge, even while the session remains anonymous.
limits()
guess,correct=start_login();wrong='000000' if correct!='000000' else '000001'
for _ in range(5):guess.call('/auth/two-factor/verify',{'code':wrong},status=422)
guess.call('/auth/two-factor/verify',{'code':correct},status=422)
assert guess.call('/auth/me')['user'] is None
# Codes expire, and newly issued challenges cannot bypass account-level send limits.
limits()
expired,code=start_login()
php('$db=require "php/db.php";$q=$db->prepare("UPDATE two_factor_challenges SET expires_at=? WHERE purpose=?");$q->execute([time()-1,"login"]);')
expired.call('/auth/two-factor/verify',{'code':code},status=422)
limits()
throttled,_=start_login()
throttled.call('/auth/two-factor/resend',{})
throttled.call('/auth/two-factor/resend',{})
throttled.call('/auth/two-factor/resend',{},status=429)
# Cancellation revokes the pending challenge and allows password login to start over.
throttled.call('/auth/two-factor/cancel',{})
assert throttled.call('/auth/me')['two_factor_pending'] is False
# If mail is broken, an opted-in account cannot fall back to password-only login.
limits();enabled(False)
assert php('require "php/functions/mail.php";echo (int)mailConfiguration()["enabled"];')=='0'
failed=Client();failed.call('/auth/me');failed.call('/auth/login',{'email':address,'password':password},status=503)
assert failed.call('/auth/me')['user'] is None
assert sign_in(plain)[1]['user']
enabled();limits()
# A ban/session-version change invalidates a pending challenge.
banned,code=start_login()
php('$db=require "php/db.php";$q=$db->prepare("UPDATE users SET is_active=FALSE,session_version=session_version+1 WHERE email=?");$q->execute([$argv[1]]);',address)
banned.call('/auth/two-factor/verify',{'code':code},status=422)
php('$db=require "php/db.php";$q=$db->prepare("UPDATE users SET is_active=TRUE,session_version=session_version+1 WHERE email=?");$q->execute([$argv[1]]);',address)
# Password recovery retains the opt-in flag and invalidates earlier login codes.
limits()
stale,code=start_login()
reset=php('require "php/models/PasswordReset.php";$db=require "php/db.php";$user=(new User($db))->byEmail($argv[1]);echo (new PasswordReset($db))->issue((int)$user["id"]);',address)
reset_client=Client();reset_client.call('/auth/me');reset_client.call('/auth/reset-password',{'token':reset,'password':password,'password_confirmation':password})
stale.call('/auth/two-factor/verify',{'code':code},status=422)
assert user_flag()
limits();signed,code=start_login();signed.call('/auth/two-factor/verify',{'code':code})
disable=setting(signed,False)
assert user_flag()
assert signed.call('/auth/two-factor/verify-setting',{'code':disable})['user']['two_factor_enabled'] is False
assert sign_in()[1]['user']['two_factor_enabled'] is False
# The site owner can opt in too, without requiring other users to opt in.
limits()
owner,normal=sign_in('fixture-owner@example.com','fixture-owner-password-123')
owner.call('/auth/two-factor/request-setting',{'enabled':True,'current_password':'fixture-owner-password-123'})
owner.call('/auth/two-factor/verify-setting',{'code':code_for('fixture-owner@example.com')})
assert sign_in('fixture-owner@example.com','fixture-owner-password-123')[1]['requires_two_factor'] is True
# Private terminal recovery is explicit, revokes sessions, and sends no outside mail.
recovery=subprocess.run(['php','php/commands/two-factor-recovery.php','fixture-owner@example.com','--confirm=DISABLE-2FA'],capture_output=True,text=True)
assert recovery.returncode==0,recovery.stdout+recovery.stderr
assert sign_in('fixture-owner@example.com','fixture-owner-password-123')[1]['user']['two_factor_enabled'] is False
# No challenge/code/session/password is recorded in activity summaries.
activity=php('$db=require "php/db.php";echo json_encode($db->query("SELECT summary FROM activity_log")->fetchAll(PDO::FETCH_COLUMN));')
assert password not in activity and code not in activity
print('PASS: optional password-only login, confirmed enrollment/disable, owner and Manager opt-in, local email capture, pending access denial, CSRF, session binding, expiry, attempts, resend/throttling, replay, mail failure, ban/reset revocation and terminal recovery.')
