"""Run with --local only. Creates/drops its own MySQL database using local config credentials.
Requires a Unix terminal API (pty), local MySQL and CREATE/DROP DATABASE privileges.
Never use production credentials. Existing application tables are not modified.
"""
import json
import os
from pathlib import Path
import pty
import secrets
import select
import shutil
import subprocess
import sys
import tempfile
import time


def main():
    if sys.argv[1:] != ['--local']:
        raise SystemExit('Usage: python3 scripts/installation-e2e.py --local (development credentials only)')
    root = Path(__file__).resolve().parent.parent
    database = 'install_test_' + secrets.token_hex(8)
    password = secrets.token_urlsafe(24)

    def php(code, *args):
        return subprocess.check_output(['php', '-r', code, *args], cwd=root, text=True).strip()

    config = json.loads(php('echo json_encode(require "php/config.php");'))
    if config.get('db_type', 'mysql') != 'mysql':
        raise SystemExit('This integration test requires local MySQL.')
    php('$db=require "php/db.php";$db->exec("CREATE DATABASE `".$argv[1]."` CHARACTER SET utf8mb4");', database)
    try:
        with tempfile.TemporaryDirectory(prefix='total-install-') as directory:
            fixture = Path(directory)
            shutil.copytree(root/'php', fixture/'php', ignore=shutil.ignore_patterns('config.php', '*.config.local.php', '.migrate.lock', 'storage'))
            source = root/'dist' if (root/'dist/index.html').exists() else root/'public'
            shutil.copytree(source, fixture/'public')
            os.mkdir(fixture/'php/storage')
            command = ['php', str(fixture/'php/commands/install.php')]

            def install(answers):
                master, slave = pty.openpty()
                def terminal():
                    import fcntl, termios
                    os.setsid()
                    fcntl.ioctl(0, termios.TIOCSCTTY, 0)
                process = subprocess.Popen(command, stdin=slave, stdout=slave, stderr=slave, cwd=fixture, close_fds=True, preexec_fn=terminal)
                os.close(slave)
                transcript = b''
                try:
                    for marker, answer in answers:
                        deadline = time.monotonic()+30
                        while marker.encode() not in transcript:
                            if time.monotonic()>deadline:
                                raise AssertionError('Installer prompt timeout: '+marker)
                            ready, _, _ = select.select([master], [], [], .1)
                            if ready:
                                try:
                                    chunk = os.read(master, 65536)
                                except OSError:
                                    chunk = b''
                                if not chunk:
                                    raise AssertionError('Installer ended before prompt: '+marker+'; '+transcript.decode(errors='replace').replace(config.get('password','') or 'UNUSED_SECRET', '[redacted]'))
                                transcript += chunk
                        # Allow the terminal's echo-off operation to complete.
                        time.sleep(.05)
                        os.write(master, (answer+'\n').encode())
                        transcript = b''
                    deadline = time.monotonic()+45
                    while process.poll() is None:
                        if time.monotonic()>deadline:
                            raise AssertionError('Installer completion timeout')
                        ready, _, _ = select.select([master], [], [], .1)
                        if ready:
                            try:
                                transcript += os.read(master,65536)
                            except OSError:
                                break
                    result = process.wait(timeout=5)
                    assert result == 0, 'Installation failed (inspect fixture in a manual run).'
                finally:
                    if process.poll() is None:
                        process.kill()
                        process.wait()
                    os.close(master)

            install([
                ('Database host', config['host']), ('Database port', str(config.get('port',3306))),
                ('Database name', database), ('Database username', config['username']),
                ('Database password', config.get('password','')),
                ('Website root URL', 'https://installation.example'),
                ('Password-reset sender email', 'noreply@installation.example'),
                ('Absolute public directory', str(fixture/'public')),
                ('Run migrations now?', 'yes'), ('Owner name', 'Installation Test'),
                ('Owner email', 'owner@installation.example'), ('Owner password', password),
                ('Confirm owner password', password),
            ])
            private = fixture/'php/config.php'
            assert private.stat().st_mode & 0o077 == 0
            before = {p.name:p.read_bytes() for p in (fixture/'php').glob('*.config.local.php')}
            before['config.php']=private.read_bytes()
            install([('Run migrations now?', 'yes')])
            for name, contents in before.items():
                assert (fixture/'php'/name).read_bytes()==contents, 'Existing configuration changed'
            result = subprocess.run(['php', str(fixture/'php/commands/health-check.php'),'--production','--json'], capture_output=True,text=True)
            report=json.loads(result.stdout)
            assert result.returncode==0 and report['ok'], [(c['name'],c['message']) for c in report['checks'] if c['status']=='fail']
            owner=json.loads(php('$db=require $argv[1];$row=$db->query("SELECT name,email,password_hash FROM users")->fetchAll();echo json_encode([count($row),password_verify($argv[2],$row[0]["password_hash"])]);',str(fixture/'php/db.php'),password))
            assert owner==[1,True]
            # Pending migrations must be reported without being applied.
            (fixture/'php/migrations/999_health_fixture.php').write_text('<?php return [];')
            result=subprocess.run(['php',str(fixture/'php/commands/health-check.php'),'--json'],capture_output=True,text=True)
            assert result.returncode==1 and any(c['name']=='migrations' and c['status']=='fail' for c in json.loads(result.stdout)['checks'])
            print('PASS: fresh installation, hidden password prompts, migrations, one owner, safe rerun, private permissions, production health and pending-migration detection.')
    finally:
        php('$db=require "php/db.php";$db->exec("DROP DATABASE `".$argv[1]."`");',database)
        print('Disposable installation database and files removed.')


if __name__ == '__main__':
    main()
