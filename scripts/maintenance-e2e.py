"""Development only. Isolated maintenance HTTP/locking and retention tests; never use production credentials."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.request
import urllib.error
import uuid


def main():
    if sys.argv[1:] != ['--local']:
        raise SystemExit('Usage: python3 scripts/maintenance-e2e.py --local (development database credentials only).')
    root=Path(__file__).resolve().parent.parent
    database='maint_test_'+uuid.uuid4().hex[:16]
    def php(code,*args):
        return subprocess.check_output(['php','-r',code,*map(str,args)],cwd=root,text=True).strip()
    php('$db=require "php/db.php";$db->exec("CREATE DATABASE `".$argv[1]."` CHARACTER SET utf8mb4");',database)
    server=None
    try:
        with tempfile.TemporaryDirectory(prefix='total-maint-') as temporary:
            fixture=Path(temporary)
            shutil.copytree(root/'php',fixture/'php',ignore=shutil.ignore_patterns('config.php','*.config.local.php','storage','.migrate.lock'))
            shutil.copytree(root/'public',fixture/'public')
            (fixture/'php/storage').mkdir()
            php('require "php/console/cli.php";$config=require "php/config.php";$config["database"]=$argv[2];writePrivateConfig($argv[1],$config);',fixture/'php/config.php',database)
            def command(name,*args,expected=0):
                r=subprocess.run(['php',str(fixture/'php/commands'/name),*args],capture_output=True,text=True)
                assert r.returncode==expected,r.stdout+r.stderr
                return r
            r=subprocess.run(['php',str(fixture/'php/migrate.php'),'migrate'],capture_output=True,text=True)
            assert r.returncode==0,r.stdout+r.stderr
            assert json.loads(command('maintenance.php','status','--json').stdout)=={'enabled':False}
            router=fixture/'router.php'
            router.write_text('<?php $path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);if(str_starts_with($path,"/api/"))require __DIR__."/public/api/index.php";else require __DIR__."/public/index.php";')
            with socket.socket() as sock:
                sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
            server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(fixture/'public'),str(router)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
            def request(path,method='GET',data=None):
                req=urllib.request.Request(f'http://127.0.0.1:{port}'+path,data=data,method=method,headers={'Content-Type':'application/json'})
                try:
                    r=urllib.request.urlopen(req,timeout=4)
                except urllib.error.HTTPError as error:
                    r=error
                return r.status,r.headers,r.read()
            for _ in range(50):
                try:
                    if request('/api/auth/me')[0]==200:break
                except OSError:time.sleep(.05)
            else:raise AssertionError('Fixture PHP server did not start')
            # A request holding a shared gate must finish before down succeeds.
            held=subprocess.Popen(['php','-r','require $argv[1];MaintenanceMode::enterRequest(true);echo "ready\\n";flush();usleep(1100000);',str(fixture/'php/maintenance/Mode.php')],stdout=subprocess.PIPE,text=True)
            assert held.stdout.readline().strip()=='ready'
            start=time.monotonic()
            command('maintenance.php','down','--message=<script>test</script> Temporary work','--retry-after=120','--wait=5')
            assert time.monotonic()-start>.7
            held.wait(timeout=3)
            for path,method,data in [('/', 'GET',None),('/site-admin/login','GET',None),('/api/auth/me','GET',None),('/api/contacts','POST',b'{}'),('/api/subscribers','POST',b'{}')]:
                status,headers,body=request(path,method,data)
                assert status==503 and headers['Retry-After']=='120' and 'no-store' in headers['Cache-Control']
                if path.startswith('/api/'):
                    result=json.loads(body);assert result['maintenance'] is True
                else:assert b'&lt;script&gt;' in body and b'<script>test' not in body
            # Mode must still work if the DB configuration is absent.
            (fixture/'php/config.php').rename(fixture/'php/config.saved')
            assert request('/api/auth/me')[0]==503
            (fixture/'php/config.saved').rename(fixture/'php/config.php')
            # No bypass through an Admin URL or a guessed query token.
            assert request('/api/users?maintenance_bypass=1')[0]==503
            holder=subprocess.Popen(['php','-r','require $argv[1];$locks=MaintenanceMode::commandLock();echo "ready\\n";flush();usleep(900000);',str(fixture/'php/maintenance/Mode.php')],stdout=subprocess.PIPE,text=True)
            assert holder.stdout.readline().strip()=='ready'
            command('maintenance.php','up',expected=1)
            assert json.loads(command('maintenance.php','status','--json').stdout)['enabled'] is True
            holder.wait(timeout=3)
            command('maintenance.php','up')
            assert request('/api/auth/me')[0]==200
            command('maintenance.php','up')
            command('maintenance.php','down','--retry-after=0',expected=1)
            # A drain timeout leaves the marker enabled; it must not reopen traffic.
            stalled=subprocess.Popen(['php','-r','require $argv[1];MaintenanceMode::enterRequest(true);echo "ready\\n";flush();usleep(700000);',str(fixture/'php/maintenance/Mode.php')],stdout=subprocess.PIPE,text=True)
            assert stalled.stdout.readline().strip()=='ready'
            command('maintenance.php','down','--wait=0',expected=1)
            assert json.loads(command('maintenance.php','status','--json').stdout)['enabled'] is True
            stalled.wait(timeout=3)
            command('maintenance.php','down','--wait=1')
            command('maintenance.php','up')

            # Seed only disposable retention fixtures.
            php('''$db=require $argv[1];$now=time();$q=$db->prepare("INSERT INTO password_resets(user_id,token_hash,expires_at)VALUES(?,?,?)");$q->execute([1001,str_repeat("a",64),$now-1]);$q->execute([1002,str_repeat("b",64),$now+1800]);$q=$db->prepare("INSERT INTO rate_limits(identifier,attempts,window_start)VALUES(?,?,?)");$q->execute([str_repeat("c",64),3,$now-3*86400]);$q->execute([str_repeat("d",64),3,$now-100]);$q=$db->prepare("INSERT INTO activity_log(actor_name,module,action,target_id,summary,created_at)VALUES(?,?,?,?,?,?)");for($i=0;$i<4;$i++)$q->execute(["Maintenance Test","test","fixture","test","Disposable",gmdate("Y-m-d H:i:s",$now-91*86400)]);$q->execute(["Maintenance Test","test","fixture","test","Recent",gmdate("Y-m-d H:i:s",$now)]);''',fixture/'php/db.php')
            dry=json.loads(command('cleanup.php','--json').stdout)
            assert dry['dry_run'] and dry['tables']['resets']['eligible']==1 and dry['tables']['rate-limits']['eligible']==1 and dry['tables']['activity']['eligible']==4
            assert all(row['deleted']==0 for row in dry['tables'].values())
            local=fixture/'php/maintenance.config.local.php'
            local.write_text('<?php return ["batch_size"=>1,"max_rows_per_table"=>2];')
            first=json.loads(command('cleanup.php','--apply','--json').stdout)
            assert first['tables']['resets']['deleted']==1 and first['tables']['rate-limits']['deleted']==1
            assert first['tables']['activity']['deleted']==2 and first['tables']['activity']['remaining']==2
            second=json.loads(command('cleanup.php','--apply','--only=activity','--json').stdout)
            assert second['tables']['activity']['deleted']==2
            retained=json.loads(php('$db=require $argv[1];echo json_encode([(int)$db->query("SELECT COUNT(*) FROM password_resets")->fetchColumn(),(int)$db->query("SELECT COUNT(*) FROM rate_limits")->fetchColumn(),(int)$db->query("SELECT COUNT(*) FROM activity_log")->fetchColumn()]);',fixture/'php/db.php'))
            assert retained==[1,1,1]
            local.write_text('<?php return ["activity_days"=>0];')
            disabled=json.loads(command('cleanup.php','--apply','--only=activity','--json').stdout)
            assert disabled['tables']['activity']['disabled'] is True
            local.write_text('<?php return ["rate_limit_days"=>0];')
            command('cleanup.php','--apply',expected=1)
            command('cleanup.php','--apply','--dry-run',expected=1)
            command('cleanup.php','--only=users',expected=1)
            print('PASS: PHP/API 503, safe message rendering, Retry-After, no DB dependency/bypass, request draining, protected reopening, resume, dry-run/apply, batching, retention and active-record preservation.')
    finally:
        if server:
            server.terminate();server.wait(timeout=5)
        php('$db=require "php/db.php";$db->exec("DROP DATABASE `".$argv[1]."`");',database)
        print('Disposable maintenance database and files removed. Main application maintenance state was not changed.')


if __name__=='__main__':
    main()
