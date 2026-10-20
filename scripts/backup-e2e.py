"""Development only: isolated MySQL backup/restore round trip. Never use production credentials."""
import base64
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import uuid
import zipfile


def main():
    if sys.argv[1:] != ['--local']:
        raise SystemExit('Usage: python3 scripts/backup-e2e.py --local; local development credentials with CREATE/DROP DATABASE required.')
    root=Path(__file__).resolve().parent.parent
    database='backup_test_'+uuid.uuid4().hex[:16]
    def php(code,*args):
        return subprocess.check_output(['php','-r',code,*map(str,args)],cwd=root,text=True).strip()
    php('$db=require "php/db.php";$db->exec("CREATE DATABASE `".$argv[1]."` CHARACTER SET utf8mb4");',database)
    try:
        with tempfile.TemporaryDirectory(prefix='total-backup-') as folder:
            folder=Path(folder)
            shutil.copytree(root/'php',folder/'php',ignore=shutil.ignore_patterns('config.php','*.config.local.php','storage','.migrate.lock'))
            shutil.copytree(root/'public',folder/'public')
            (folder/'php/storage').mkdir()
            php('require "php/console/cli.php";$config=require "php/config.php";$config["database"]=$argv[2];writePrivateConfig($argv[1],$config);',folder/'php/config.php',database)
            def command(name,*args,expected=0):
                result=subprocess.run(['php',str(folder/'php/commands'/name),*map(str,args)],capture_output=True,text=True)
                assert result.returncode==expected, result.stdout+result.stderr
                return result
            result=subprocess.run(['php',str(folder/'php/migrate.php'),'migrate'],capture_output=True,text=True)
            assert result.returncode==0,result.stdout+result.stderr
            php('require $argv[1]."/models/User.php";$db=require $argv[1]."/db.php";(new User($db))->create("Backup Owner","backup@example.com","fixture-password-12345");$q=$db->prepare("INSERT INTO subscribers(email,created_at) VALUES (?,?)");$q->execute(["quote\x27+test@example.com","2026-01-01 00:00:00"]);',folder/'php')
            php('$db=require $argv[1];$db->exec("UPDATE users SET two_factor_enabled=1");$q=$db->prepare("INSERT INTO two_factor_challenges(id,user_id,purpose,session_version,code_hash,expires_at)VALUES(?,?,?,?,?,?)");$q->execute([str_repeat("a",64),1,"login",1,password_hash("123456",PASSWORD_DEFAULT),time()+600]);',folder/'php/db.php')
            uploads=folder/'public/uploads'
            fixture=uploads/'restore-test.png'
            image=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aA1sAAAAASUVORK5CYII=')
            fixture.write_bytes(image)
            command('backup.php',expected=1)
            # Public destination and symlinked uploads must be rejected.
            command('backup.php','--offline','--directory='+str(folder/'public/backups'),expected=1)
            assert not (folder/'public/backups').exists()
            command('backup.php','--offline',expected=1)
            command('maintenance.php','down')
            command('backup.php','--offline','--directory='+str(folder/'public/backups'),expected=1)
            result=command('backup.php','--offline')
            backups=folder/'php/storage/backups'
            archive=next(backups.glob('*.zip'))
            assert archive.stat().st_mode & 0o077==0
            with zipfile.ZipFile(archive) as z:
                manifest=json.loads(z.read('manifest.json'))
                assert manifest['format']==1
                assert 'config/config.php' in manifest['files']
                for name,entry in manifest['files'].items():
                    assert hashlib.sha256(z.read(name)).hexdigest()==entry['sha256']
            command('restore.php',archive,'--verify')
            command('restore.php',archive,'--offline',expected=1)
            # Corruption must fail before any import or recovery backup.
            damaged=folder/'damaged.zip'
            with zipfile.ZipFile(archive) as src,zipfile.ZipFile(damaged,'w') as dst:
                for name in src.namelist():
                    dst.writestr(name,b'bad SQL' if name=='database.sql' else src.read(name))
            command('restore.php',damaged,'--offline','--confirm=RESTORE',expected=1)
            assert len(list(backups.glob('*.zip')))==1
            # Traversal/extra members are also rejected.
            traversal=folder/'traversal.zip'
            shutil.copyfile(archive,traversal)
            with zipfile.ZipFile(traversal,'a') as z:
                z.writestr('uploads/../../escape','invalid')
            command('restore.php',traversal,'--verify',expected=1)
            crafted=folder/'crafted.zip'
            with zipfile.ZipFile(archive) as src,zipfile.ZipFile(crafted,'w') as dst:
                metadata=json.loads(src.read('manifest.json'))
                old='uploads/restore-test.png'
                bad='uploads/../../escape'
                metadata['files'][bad]=metadata['files'].pop(old)
                for name in src.namelist():
                    if name=='manifest.json':
                        dst.writestr(name,json.dumps(metadata))
                    else:
                        dst.writestr(bad if name==old else name,src.read(name))
            command('restore.php',crafted,'--verify',expected=1)
            assert not (backups/'escape').exists()

            php('$db=require $argv[1];$db->exec("DELETE FROM subscribers");$db->exec("UPDATE users SET session_version=50");$db->exec("CREATE TABLE added_after_backup(id INTEGER PRIMARY KEY)");',folder/'php/db.php')
            fixture.write_bytes(b'changed')
            (uploads/'later-file.png').write_bytes(b'later')
            original_config=(folder/'php/config.php').read_bytes()
            command('restore.php',archive,'--offline','--confirm=RESTORE')
            assert fixture.read_bytes()==image
            assert not (uploads/'later-file.png').exists()
            assert (folder/'php/config.php').read_bytes()==original_config
            rows=json.loads(php('$db=require $argv[1];echo json_encode([$db->query("SELECT email FROM subscribers")->fetchAll(PDO::FETCH_COLUMN),(int)$db->query("SELECT session_version FROM users LIMIT 1")->fetchColumn()]);',folder/'php/db.php'))
            assert rows[0]==["quote'+test@example.com"] and rows[1]>50
            factor=json.loads(php('$db=require $argv[1];echo json_encode([(int)$db->query("SELECT two_factor_enabled FROM users LIMIT 1")->fetchColumn(),(int)$db->query("SELECT COUNT(*) FROM two_factor_challenges")->fetchColumn()]);',folder/'php/db.php'))
            assert factor==[1,0], 'Restore must preserve opt-in but discard restored verification challenges'

            assert len(list(backups.glob('*.zip')))==2
            assert php('$db=require $argv[1];echo (int)($db->query("SHOW TABLES LIKE \'added_after_backup\'")->fetchColumn()===false);',folder/'php/db.php')=='1' 
            # Optional private config replacement restores an archived non-DB setting.
            config=folder/'php/auth.config.local.php'
            config.write_text('<?php return ["app_url"=>"https://before.example"];')
            command('backup.php','--offline')
            archive2=max(backups.glob('*.zip'),key=lambda p:p.stat().st_mtime_ns)
            config.write_text('<?php return ["app_url"=>"https://after.example"];')
            command('restore.php',archive2,'--offline','--confirm=RESTORE','--with-config')
            assert 'before.example' in config.read_text()
            # Disaster recovery onto an empty configured database also works.
            php('$db=require $argv[1];$tables=$db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);$db->exec("SET FOREIGN_KEY_CHECKS=0");foreach($tables as $table)$db->exec("DROP TABLE `".str_replace("`","``",$table)."`");',folder/'php/db.php')
            command('restore.php',archive,'--offline','--confirm=RESTORE')
            assert php('$db=require $argv[1];echo $db->query("SELECT COUNT(*) FROM users")->fetchColumn();',folder/'php/db.php')=='1'
            # Simulate an import failure after successful preflight/recovery backup.
            client=folder/'failing-mysql'
            client.write_text('#!/bin/sh\nfor arg in "$@"; do case "$arg" in --execute=*) exit 0;; esac; done\nexit 1\n')
            client.chmod(0o700)
            failed=command('restore.php',archive,'--offline','--confirm=RESTORE','--mysql='+str(client),expected=1)
            recovery=next(line.split(': ',1)[1] for line in failed.stdout.splitlines() if line.startswith('Pre-restore recovery backup: '))
            assert Path(recovery).exists()
            command('restore.php',recovery,'--offline','--confirm=RESTORE')
            assert php('$db=require $argv[1];echo $db->query("SELECT COUNT(*) FROM users")->fetchColumn();',folder/'php/db.php')=='1'
            assert not list(backups.glob('.work-*'))
            assert not list(backups.glob('*.partial'))
            print('PASS: database/uploads/config backup, checksums, permissions, private destination, verification, confirmation, corruption/traversal rejection, data round trip, recovery backup, session revocation and optional config restore.')
    finally:
        php('$db=require "php/db.php";$db->exec("DROP DATABASE `".$argv[1]."`");',database)
        print('Disposable backup database/files removed. Existing application data was not restored or changed.')


if __name__=='__main__':
    main()
