"""Helpers for cases run exclusively inside scripts/integration-e2e.py fixtures."""
import http.cookiejar
import json
import os
from pathlib import Path
import subprocess
import urllib.request
import urllib.error

if Path(os.environ.get('TOTAL_INTEGRATION_FIXTURE','/')).resolve() != Path.cwd().resolve() or not (Path.cwd()/'.integration-fixture').is_file():
    raise SystemExit('Use the isolated integration runner.')
BASE=os.environ['TOTAL_TEST_BASE']


def php(code,*args):
    return subprocess.check_output(['php','-r',code,*map(str,args)],text=True).strip()


class Client:
    def __init__(self):
        self.jar=http.cookiejar.CookieJar()
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.token=''

    def call(self,path,data=None,method=None,status=200,csrf=True):
        headers={'Content-Type':'application/json'}
        if csrf:headers['X-CSRF-Token']=self.token
        request=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers=headers,method=method)
        try:
            with self.op.open(request,timeout=20) as response:
                code,body=response.status,response.read()
        except urllib.error.HTTPError as response:
            code,body=response.code,response.read()
        result=json.loads(body)
        assert code==status,(path,code,result)
        if result.get('csrf'):self.token=result['csrf']
        return result
