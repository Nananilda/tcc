"""Revisão 3 — integração em banco DESCARTÁVEL. Cria registros, não use em produção.
Uso: python3 tests/revisao_test.py http://127.0.0.1:8000
"""
import urllib.request as U, urllib.error, urllib.parse, json, re, time, http.cookiejar, os, csv, io
from pathlib import Path
BASE=__import__('sys').argv[1].rstrip('/')
checks=0
PREFIX='rev'+str(time.time_ns())[-10:]
def req(path, data=None, token=None, opener=None, headers=None):
    h=headers or {}
    if token:h['Authorization']='Bearer '+token
    if isinstance(data,dict):data=json.dumps(data).encode();h['Content-Type']='application/json'
    q=U.Request(BASE+path,data=data,headers=h)
    try:r=(opener.open if opener else U.urlopen)(q,timeout=60)
    except urllib.error.HTTPError as e:r=e
    return r.status,r.read()
def api(path,data=None,token=None):
    st,b=req('/api/'+path,data,token);return st,json.loads(b)
def good(path,data=None,token=None):
    st,j=api(path,data,token);assert st==200 and j.get('sucesso'),(path,st,j);return j
def check(v,text):
    global checks
    assert v,text
    checks+=1;print('PASS',text,flush=True)
admin=good('login.php',{'login':os.getenv('AKRON_TEST_LOGIN','admin'),'senha':os.getenv('AKRON_TEST_PASS','Admin@123')})['token']
principal=good('conta.php',token=admin)['usuario']
check(principal['administrador_principal']==1,'administrador principal identificado pela migração')
check(api('usuarios.php',{'acao':'atualizar','id':principal['id'],'nome':'Alterado','login':'outro','tipo':'usuario','status':'inativo'},admin)[0]==422,'edição do principal bloqueada no backend')
check(api('usuarios.php',{'acao':'desativar','id':principal['id']},admin)[0]==422,'desativação do principal bloqueada')
e=good('estoques.php',{'acao':'cadastrar','nome':PREFIX+' Galpão','localizacao':'Setor A','descricao':'Materiais de teste'},admin)['id']
outro=good('estoques.php',{'acao':'cadastrar','nome':PREFIX+' Restrito'},admin)['id']
uid=good('usuarios.php',{'acao':'cadastrar','nome':'Pessoa Revisora','login':PREFIX,'senha':'Teste@12345','confirmar':'Teste@12345','tipo':'usuario','status':'ativo','estoque_ids':[e]},admin)['id']
usr=good('login.php',{'login':PREFIX,'senha':'Teste@12345'})['token']
check(api('conta.php',{'nome':'Pessoa Revisora','tipo':'admin'},usr)[0]==422,'Minha conta não permite elevação de privilégios')
check(api('conta.php',{'nome':'Pessoa Revisora','id':principal['id']},usr)[0]==422,'Minha conta não aceita identidade de outra pessoa')
good('conta.php',{'nome':'Pessoa Atualizada','tema':'escuro'},usr)
check(good('conta.php',token=usr)['usuario']['tema']=='escuro' and good('conta.php',token=admin)['usuario']['tema']==principal['tema'],'tema persistido individualmente')
good('conta.php',{'acao':'tema','tema':'automatico'},usr)
check(good('conta.php',token=usr)['usuario']['tema']=='automatico','tema automático salvo')
check(api('conta.php',{'nome':'Pessoa Atualizada','senha_atual':'errada','senha':'Nova@12345','confirmar_senha':'Nova@12345'},usr)[0]==422,'troca de senha exige senha atual')
good('conta.php',{'nome':'Pessoa Atualizada','senha_atual':'Teste@12345','senha':'Nova@12345','confirmar_senha':'Nova@12345'},usr)
check(api('login.php',{'login':PREFIX,'senha':'Teste@12345'})[0]==401,'senha antiga recusada após alteração')
usr=good('login.php',{'login':PREFIX,'senha':'Nova@12345'})['token'];check(True,'login funciona com nova senha')
def upload(content,filename,mime):
    boundary='AkronBoundary123';b=(f'--{boundary}\r\nContent-Disposition: form-data; name="nome"\r\n\r\nPessoa Atualizada\r\n--{boundary}\r\nContent-Disposition: form-data; name="foto"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode()+content+f'\r\n--{boundary}--\r\n'.encode()
    return req('/api/conta.php',b,usr,headers={'Content-Type':'multipart/form-data; boundary='+boundary})
foto=(Path(__file__).resolve().parents[1]/'public/assets/img/favicon-32.png').read_bytes()
check(upload(foto,'foto.png','image/png')[0]==200,'upload de foto PNG validado e convertido')
check(good('conta.php',token=usr)['usuario']['foto'].startswith('data:image/png;base64,'),'foto individual persistida')
check(upload(b'<svg onload="alert(1)"></svg>','foto.svg','image/svg+xml')[0]==422,'arquivo de foto inválido recusado')
check(upload(foto,'substituida.png','image/png')[0]==200,'substituição de foto')
good('conta.php',{'nome':'Pessoa Atualizada','remover_foto':'1'},usr)
check(good('conta.php',token=usr)['usuario']['foto'] is None,'remoção da foto e retorno ao avatar')
sensores=[]
for tipo in ['temperatura','umidade','qualidade_ar','ruido','pressao','uv']:
    good('sensores.php',{'acao':'cadastrar','nome':PREFIX+' '+tipo,'tipo':tipo,'estoque_id':e,'status':'ativo','limite_atencao_max':30,'limite_critico_max':35,'intervalo_comunicacao':3600},admin)
    sid=[s['id'] for s in good('sensores.php?estoque_id='+str(e),token=admin)['sensores'] if s['tipo']==tipo][0];sensores.append(sid)
    for v in [20,40,22]:good('leituras.php',{'sensor_id':sid,'valor':v},admin)
for i in range(27):good('alertas.php',{'acao':'criar','sensor_id':sensores[0],'severidade':'atencao','mensagem':PREFIX+' ocorrência '+str(i),'valor':31},admin)
for i in range(30):good('leituras.php',{'sensor_id':sensores[0],'valor':21},admin)
# Cookie web: valida telas, exportações e paginação com sessão real.
def web_login(login,password):
    op=U.build_opener(U.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    st,b=req('/index.php',opener=op);csrf=re.search(rb'name="csrf_token" value="([^"]+)"',b)[1].decode()
    st,b=req('/index.php',urllib.parse.urlencode({'login':login,'senha':password,'csrf_token':csrf}).encode(),opener=op,headers={'Content-Type':'application/x-www-form-urlencoded'})
    assert st==200 and b'Fatal error' not in b
    return op
web=web_login(PREFIX,'Nova@12345')
for path in ['/app/views/usuarios/perfil.php','/app/views/estoques/listar.php','/app/views/estoques/detalhes.php?id='+str(e),'/app/views/sensores/detalhes.php?id='+str(sensores[0]),'/app/views/graficos/graficos_sensores.php','/app/views/graficos/alerta.php','/app/views/relatorios/relatorio_qualidade.php']:
    st,b=req(path,opener=web);check(st==200 and b'Fatal error' not in b and b'Warning:' not in b,'página sem erros '+path)
st,b=req('/routes/grafico_dados.php?todos=1&estoque_id='+str(e),opener=web);d=json.loads(b)
check(st==200 and len(d['series'])==6 and len(d['atuais'])==6,'monitoramento inclui os seis tipos com unidades separadas')
for offset,expected in [(5,10),(15,10),(25,8)]:
    st,b=req(f'/routes/historico.php?tipo_lista=alertas&estoque_id={e}&offset={offset}',opener=web);d=json.loads(b)
    check(st==200 and d['quantidade']==expected and d['total']==33,'alertas progressivos a partir de '+str(offset))
st,b=req(f'/routes/historico.php?tipo_lista=leituras&estoque_id={e}&offset=25',opener=web)
check(st==200 and json.loads(b)['quantidade']==23,'leituras progressivas sem perda de registros')
check(req(f'/routes/historico.php?tipo_lista=alertas&estoque_id={outro}&offset=0',opener=web)[0]==422,'histórico progressivo rejeita estoque não autorizado')
for entidade,ident in [('estoque',e),('sensor',sensores[0])]:
    for formato in ['csv','pdf']:
        st,b=req(f'/routes/exportar.php?entidade={entidade}&id={ident}&formato={formato}',opener=web)
        check(st==200 and (b.startswith(b'%PDF-') if formato=='pdf' else 'IDENTIFICAÇÃO' in b.decode('utf-8-sig')),f'exportação {entidade} {formato}')
        if formato=='csv':check(PREFIX.encode() in b and b'31' in b,'exportação inclui identidade e alertas')
st,b=req(f'/routes/exportar.php?entidade=alertas&estoque_id={e}&status=pendente&formato=csv',opener=web)
rows=list(csv.reader(io.StringIO(b.decode('utf-8-sig')),delimiter=';'));check(st==200 and len(rows)==28,'CSV inclui todos os 27 alertas filtrados')
st,b=req(f'/routes/exportar.php?entidade=alertas&estoque_id={outro}&formato=csv',opener=web);check(len(list(csv.reader(io.StringIO(b.decode('utf-8-sig')),delimiter=';')))==1,'CSV não vaza alertas de outro estoque')
check(req(f'/routes/exportar.php?entidade=estoque&id={outro}&formato=pdf',opener=web)[0]==403,'dossiê de estoque não autorizado bloqueado')
adminweb=web_login(os.getenv('AKRON_TEST_LOGIN','admin'),os.getenv('AKRON_TEST_PASS','Admin@123'))
for path in ['usuarios/gestao_usuarios.php','usuarios/editar.php?id='+str(principal['id']),'usuarios/cadastro.php','sensores/editar_sensores.php','estoques/formulario.php']:
    st,b=req('/app/views/'+path,opener=adminweb);check(st==200 and b'Fatal error' not in b,'tela administrativa '+path)
context={'estoque':e,'sensor':sensores[0],'usuario':uid,'login':PREFIX,'outro':outro,'principal':principal['id']}
out=Path(os.getenv('AKRON_TEST_OUTPUT','/tmp/akron-revisao'));out.mkdir(parents=True,exist_ok=True);(out/'context.json').write_text(json.dumps(context))
print(f'{checks} verificações da revisão passaram. Contexto em {out}/context.json')
