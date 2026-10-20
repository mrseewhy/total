"""Permanent isolated HTTP integration tests. Creates/drops local fixture databases only."""
import argparse
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
import uuid

CASES=['auth','users','content','services','metadata_activity','dashboard','submissions','two_factor','security']


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--local',action='store_true',required=True,help='Confirm disposable local MySQL test databases may be created and dropped.')
    parser.add_argument('--suite',choices=CASES+['all'],default='all')
    args=parser.parse_args()
    root=Path(__file__).resolve().parent.parent
    if not (root/'dist/index.html').is_file() or not (root/'dist/route-manifest.json').is_file():
        raise SystemExit('Run npm run build before integration tests.')

    def php(code,*values,cwd=root):
        return subprocess.check_output(['php','-r',code,*map(str,values)],cwd=cwd,text=True).strip()

    config=json.loads(php('echo json_encode(require "php/config.php");'))
    if config.get('db_type','mysql')!='mysql' or config.get('host','localhost').lower() not in ['localhost','127.0.0.1','::1']:
        raise SystemExit('The integration runner requires local MySQL configuration. Remote database hosts are rejected.')
    # Explicitly disable ordinary outgoing mail; the 2FA fixture uses a local capture transport.
    environment={**os.environ,'MAIL_ENABLED':'0','MAIL_FROM':'','MAIL_FROM_NAME':'','MAIL_REPLY_TO':'','APP_URL':'http://localhost:5173','SITE_URL':'http://localhost:5173','SESSION_SECURE':'0'}
    for case in CASES if args.suite=='all' else [args.suite]:
        database='integration_'+uuid.uuid4().hex[:20]
        server=None
        php('$db=require "php/db.php";$db->exec("CREATE DATABASE `".$argv[1]."` CHARACTER SET utf8mb4");',database)
        print('RUN:',case,flush=True)
        try:
            with tempfile.TemporaryDirectory(prefix='total-integration-') as directory:
                fixture=Path(directory)
                shutil.copytree(root/'php',fixture/'php',ignore=shutil.ignore_patterns('config.php','*.config.local.php','storage','.migrate.lock'))
                shutil.copytree(root/'dist',fixture/'public',ignore=shutil.ignore_patterns('uploads'))
                # Always use the current PHP source even if the frontend build predates it.
                shutil.copyfile(root/'public/index.php',fixture/'public/index.php')
                shutil.copyfile(root/'public/api/index.php',fixture/'public/api/index.php')
                (fixture/'public/uploads').mkdir(exist_ok=True)
                shutil.copyfile(root/'public/uploads/.htaccess',fixture/'public/uploads/.htaccess')
                (fixture/'php/storage').mkdir()
                (fixture/'php/storage/sessions').mkdir(mode=0o700)
                (fixture/'.integration-fixture').write_text(database)
                php('require "php/console/cli.php";$config=require "php/config.php";$config["database"]=$argv[2];writePrivateConfig($argv[1],$config);',fixture/'php/config.php',database)
                migrate=subprocess.run(['php',str(fixture/'php/migrate.php'),'migrate'],cwd=fixture,env=environment,capture_output=True,text=True)
                assert migrate.returncode==0,migrate.stdout+migrate.stderr
                php('require "php/models/User.php";$db=require "php/db.php";(new User($db))->create("Fixture Owner","fixture-owner@example.com","fixture-owner-password-123");',cwd=fixture)
                seed=subprocess.run(['php',str(fixture/'php/commands/seed-services.php')],cwd=fixture,env=environment,capture_output=True,text=True)
                assert seed.returncode==0,seed.stdout+seed.stderr
                with socket.socket() as sock:
                    sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
                origin=f'http://127.0.0.1:{port}'
                # A fixture-specific capture script is invoked by PHP mail(), not an external SMTP server.
                capture=fixture/'capture-mail.py'
                capture.write_text('import sys,uuid\nfrom pathlib import Path\nfolder=Path(__file__).parent/"mail-capture"\nfolder.mkdir(exist_ok=True)\n(folder/(uuid.uuid4().hex+".eml")).write_bytes(sys.stdin.buffer.read())\n')
                import shlex
                sendmail=f'{shlex.quote(sys.executable)} {shlex.quote(str(capture))}'
                if case=='two_factor':
                    (fixture/'php/mail.config.local.php').write_text('<?php return ["enabled"=>true,"from_address"=>"noreply@example.com","from_name"=>"Haven"];')
                router=fixture/'router.php'
                router.write_text('<?php $root=__DIR__."/public";$path=rawurldecode(parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH)?:"/");if(preg_match("#(?:^|/)\\\\.#",$path)){http_response_code(404);return;}if(str_starts_with($path,"/api/")){require $root."/api/index.php";return;}$file=realpath($root.$path);if($file&&str_starts_with($file,$root."/")&&is_file($file)&&!str_ends_with($file,".php")&&!str_ends_with($file,"index.html"))return false;require $root."/index.php";')
                server=subprocess.Popen(['php','-d','opcache.enable_cli=0','-d','opcache.enable=0','-d','session.save_path='+str(fixture/'php/storage/sessions'),'-d','sendmail_path='+sendmail,'-S',f'127.0.0.1:{port}','-t',str(fixture/'public'),str(router)],cwd=fixture,env=environment,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
                for _ in range(100):
                    try:
                        with urllib.request.urlopen(origin+'/api/auth/me',timeout=2) as response:
                            assert response.status==200
                            break
                    except OSError:
                        if server.poll() is not None:raise AssertionError('Fixture PHP server stopped')
                        time.sleep(.05)
                else:raise AssertionError('Fixture API did not become ready')
                env={**environment,'TOTAL_TEST_BASE':origin+'/api','TOTAL_TEST_ORIGIN':origin,'TOTAL_INTEGRATION_FIXTURE':str(fixture),'PYTHONDONTWRITEBYTECODE':'1'}
                if case=='security':command=[sys.executable,str(root/'scripts/security-e2e.py'),'--local']
                else:command=[sys.executable,str(root/'scripts/integration'/f'{case}.py')]
                subprocess.run(command,cwd=fixture,env=env,check=True,timeout=180)
                print('PASS:',case,flush=True)
        finally:
            if server:
                server.terminate();server.wait(timeout=5)
            php('$db=require "php/db.php";$db->exec("DROP DATABASE `".$argv[1]."`");',database)
            print('Removed disposable',case,'database and files.',flush=True)
    print('All selected integration suites passed. Existing application records, uploads and mail settings were not changed.')


if __name__=='__main__':
    main()
