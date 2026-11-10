"""Integração ÁKRON 2: banco de TESTES e servidor em execução são obrigatórios.
Cria dados com prefixos únicos e não apaga históricos. Não execute em produção.
Uso: python3 tests/api_test.py http://127.0.0.1:8000/api
Admin configurável: AKRON_TEST_LOGIN/AKRON_TEST_PASS (padrão admin/Admin@123).
AKRON_CHAVE_SENSORES opcional para testar a autenticação do dispositivo.
"""
import csv, io, json, os, sys, time, urllib.request, urllib.error
BASE = (sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8000/api').rstrip('/')
PREFIX='teste'+str(time.time_ns())[-12:]
checks=[]
def call(path, body=None, token=None, headers=None, raw=False, method=None):
    req=urllib.request.Request(BASE+'/'+path,data=None if body is None else json.dumps(body).encode(),method=method or ('GET' if body is None else 'POST'))
    if body is not None:req.add_header('Content-Type','application/json')
    if token:req.add_header('Authorization','Bearer '+token)
    for k,v in (headers or {}).items():req.add_header(k,v)
    try:
        with urllib.request.urlopen(req,timeout=40) as r:status,buf,h=r.status,r.read(),r.headers
    except urllib.error.HTTPError as e:status,buf,h=e.code,e.read(),e.headers
    if raw:return status,buf,h
    try:return status,json.loads(buf),h
    except Exception:raise AssertionError((path,status,buf[:1000]))
def ok(name,value,detail=''):
    checks.append(bool(value));print(('PASS ' if value else 'FAIL ')+name+((' '+str(detail)) if not value else ''))
    if not value:raise AssertionError(name)
def success(path,body=None,token=None):
    status,j,_=call(path,body,token);ok(path+' success',status==200 and j.get('sucesso'),j);return j
ok('status/banco',call('status.php')[1].get('banco')=='ok')
ok('anonymous denied',call('estoques.php')[0]==401)
ok('wrong password',call('login.php',{'login':'admin','senha':'errada'})[0]==401)
admin=success('login.php',{'login':os.getenv('AKRON_TEST_LOGIN','admin'),'senha':os.getenv('AKRON_TEST_PASS','Admin@123')})['token']
e1=success('estoques.php',{'acao':'cadastrar','nome':PREFIX+' autorizado','localizacao':'Sala A'},admin)['id']
e2=success('estoques.php',{'acao':'cadastrar','nome':PREFIX+' restrito','localizacao':'Sala B'},admin)['id']
user_data={'acao':'cadastrar','nome':'Pessoa de teste','login':PREFIX,'senha':'Teste@12345','confirmar':'Teste@12345','tipo':'usuario','status':'ativo','estoque_ids':[e1]}
uid=success('usuarios.php',user_data,admin)['id']
usr=success('login.php',{'login':PREFIX,'senha':'Teste@12345'})['token']
lista=success('estoques.php',token=usr)['estoques'];ok('only authorized warehouse',[e['id'] for e in lista]==[e1],lista)
ok('warehouse by ID denied',call('estoques.php?id='+str(e2),token=usr)[0]==403)
ok('ordinary user cannot write',call('estoques.php',{'acao':'cadastrar','nome':'Negado'},usr)[0]==403)
sensor={'acao':'cadastrar','nome':PREFIX+' temperatura','tipo':'temperatura','localizacao':'Prateleira','status':'ativo','estoque_id':e1,'limite_atencao_min':10,'limite_atencao_max':30,'limite_critico_min':5,'limite_critico_max':35,'intervalo_comunicacao':2}
ok('invalid bounds rejected',call('sensores.php',dict(sensor,limite_critico_min=40),admin)[0]==422)
success('sensores.php',sensor,admin)
sid=[s['id'] for s in success('sensores.php?estoque_id='+str(e1),token=admin)['sensores'] if s['nome']==sensor['nome']][0]
success('sensores.php',dict(sensor,nome=PREFIX+' restrito',estoque_id=e2),admin)
sid2=[s['id'] for s in success('sensores.php?estoque_id='+str(e2),token=admin)['sensores'] if s['nome']==PREFIX+' restrito'][0]
ok('sensor scope',len(success('sensores.php',token=usr)['sensores'])==1)
ok('sensor by ID denied',call('sensores.php?id='+str(sid2),token=usr)[0]==404)
ok('reading unauthorized',call('leituras.php',{'sensor_id':sid,'valor':25},usr)[0]==403)
ok('no device key denied',call('leituras.php',{'sensor_id':sid,'valor':25})[0]==401)
ok('invalid reading rejected',call('leituras.php',{'sensor_id':sid,'valor':'abc'},admin)[0]==422)
r=success('leituras.php',{'sensor_id':sid,'valor':25},admin);ok('normal reading no alert',r['situacao']=='normal' and r['alerta'] is None)
r=success('leituras.php',{'sensor_id':sid,'valor':30},admin);ok('threshold equality normal',r['situacao']=='normal')
r=success('leituras.php',{'sensor_id':sid,'valor':31},admin);aid=r['alerta']['id'];ok('attention alert',r['alerta']['novo'] and r['alerta']['limite_ultrapassado']==30)
r=success('leituras.php',{'sensor_id':sid,'valor':38},admin);ok('continuous escalation same alert',r['alerta']['id']==aid and not r['alerta']['novo'] and r['situacao']=='critico')
ok('user cannot acknowledge',call('alertas.php',{'acao':'reconhecer','alerta_id':aid},usr)[0]==403)
success('alertas.php',{'acao':'reconhecer','alerta_id':aid},admin)
a=success('alertas.php?sensor_id='+str(sid),token=admin)['alertas'][0];ok('recognition audit',a['status']=='reconhecido' and a['reconhecido_em'] and a['reconhecido_por'])
ok('cannot transfer active irregularity',call('sensores.php',dict(sensor,acao='atualizar',sensor_id=sid,estoque_id=e2),admin)[0]==422)
success('leituras.php',{'sensor_id':sid,'valor':24},admin)
a=success('alertas.php?sensor_id='+str(sid),token=usr)['alertas'][0];ok('automatic normalization',a['status']=='normalizado' and a['normalizado_em'] and not a['resolvido'])
success('alertas.php',{'acao':'resolver','alerta_id':aid,'observacoes':'Ventilação ajustada.'},admin)
a=success('alertas.php?sensor_id='+str(sid),token=usr)['alertas'][0];ok('resolution audit',a['status']=='resolvido' and a['resolvido_em'] and a['resolvido_por'] and a['observacoes']=='Ventilação ajustada.')
r=success('leituras.php',{'sensor_id':sid,'valor':2},admin);ok('new occurrence after normalization',r['alerta']['id']!=aid and r['situacao']=='critico')
success('leituras.php',{'sensor_id':sid,'valor':20},admin)
success('leituras.php',{'sensor_id':sid2,'valor':40},admin)
ok('alerts leak protection',success('alertas.php?estoque_id='+str(e2),token=usr)['alertas']==[])
ok('graph unauthorized rejected',call('leituras.php?estoque_id='+str(e2),token=usr)[0] in (403,422))
ok('report unauthorized rejected',call('relatorio.php?estoque_id='+str(e2),token=usr)[0] in (403,422))
key=os.getenv('AKRON_CHAVE_SENSORES')
if key:ok('device authentication',call('leituras.php',{'sensor_id':sid,'valor':22},headers={'X-Chave-Sensor':key})[0]==200)
# More than one UI page: CSV and PDF must not inherit the 200-row limit.
for i in range(205):
    st,j,_=call('leituras.php',{'sensor_id':sid,'valor':20+i/100},admin)
    if st!=200 or not j.get('sucesso'):raise AssertionError(j)
ok('205 additional readings',True)
rep=success('relatorio.php?estoque_id='+str(e1),token=usr)
ok('paginated report and whole-period stats',rep['total']>200 and len(rep['leituras'])==200,rep['total'])
ok('report rows scoped',all(l['estoque_id']==e1 for l in rep['leituras']))
st,buf,h=call('relatorio.php?estoque_id='+str(e1)+'&formato=csv',token=usr,raw=True)
rows=list(csv.reader(io.StringIO(buf.decode('utf-8-sig')),delimiter=';'));ok('complete CSV',st==200 and len(rows)-1==rep['total'],len(rows)-1)
st,buf,h=call('relatorio.php?estoque_id='+str(e1)+'&formato=pdf',token=usr,raw=True)
ok('downloadable complete PDF',st==200 and buf.startswith(b'%PDF-') and b'%%EOF' in buf and str(rep['total']).encode() in buf)
from pathlib import Path
out=Path(os.getenv('AKRON_TEST_OUTPUT','/tmp/akron-test'));out.mkdir(exist_ok=True);(out/'relatorio.pdf').write_bytes(buf)
time.sleep(3)
s=success('sensores.php?id='+str(sid),token=usr)['sensor'];ok('communication stale detected',s['comunicacao']=='sem_comunicacao')
# Moving a sensor must not move the old readings or their access rights.
success('sensores.php',dict(sensor,acao='atualizar',sensor_id=sid,estoque_id=e2),admin)
ok('current sensor no longer visible',call('sensores.php?id='+str(sid),token=usr)[0]==404)
hist=success('relatorio.php?estoque_id='+str(e1)+'&sensor_id='+str(sid),token=usr)
ok('historic data and permission preserved',hist['total']==rep['total'])
success('leituras.php',{'sensor_id':sid,'valor':23},admin)
ok('new warehouse reading hidden',success('relatorio.php',token=usr)['total']==rep['total'])
update={'acao':'atualizar','id':uid,'nome':'Pessoa de teste','login':PREFIX,'tipo':'usuario','status':'ativo','estoque_ids':[]}
success('usuarios.php',update,admin)
ok('revocation immediate',success('estoques.php',token=usr)['estoques']==[] and success('relatorio.php',token=usr)['total']==0)
success('usuarios.php',dict(update,status='inativo'),admin)
ok('inactive user token rejected',call('estoques.php',token=usr)[0]==401)
success('estoques.php',{'acao':'desativar','id':e2,'confirmacao':'desativar'},admin)
ok('inactive warehouse blocks ingestion',call('leituras.php',{'sensor_id':sid,'valor':23},admin)[0]==422)
ok('inactive warehouse blocks sensor activation',call('sensores.php',{'acao':'toggle_status','sensor_id':sid,'novo_status':'ativo'},admin)[0]==422)
print(f'\n{len(checks)} verificações passaram; registros de teste: {PREFIX}.')
(out/'context.json').write_text(json.dumps({'e1':e1,'e2':e2,'sid':sid,'sid2':sid2,'uid':uid,'prefix':PREFIX,'readings':rep['total']}))
