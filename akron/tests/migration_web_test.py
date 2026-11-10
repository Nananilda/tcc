#!/usr/bin/env python3
"""Teste de integração em uma cópia descartável do banco LEGADO.
Uso: python tests/migration_web_test.py http://127.0.0.1:8003/tcc-v2
Requer admin/Admin@123 e usuario/Usuario@123 na base de teste.
Executa a migração, portanto não use em produção.
"""
import http.cookiejar
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

base = sys.argv[1].rstrip('/')
checks = 0


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(opener, path, form=None):
    data = urllib.parse.urlencode(form).encode() if form is not None else None
    try:
        response = opener.open(base + path, data, timeout=60)
    except urllib.error.HTTPError as exc:
        response = exc
    return response.status, response.geturl(), response.read().decode()


def csrf(html):
    return re.search(r'name="csrf_token" value="([^"]+)"', html)[1]


def check(condition, description):
    global checks
    assert condition, description
    checks += 1
    print('PASS', description)


def login(opener, user, password):
    status, url, html = request(opener, '/index.php')
    return request(opener, '/index.php', {'login': user, 'senha': password, 'csrf_token': csrf(html)})


anon = client()
status, url, html = request(anon, '/atualizar-banco.php')
check(status == 200 and '/index.php' in url, 'atualização exige login')
status, url, html = request(anon, '/api/estoques.php')
check(status == 503 and json.loads(html)['codigo'] == 'BANCO_DESATUALIZADO', 'API responde manutenção JSON antes da migração')
user = client()
status, url, html = login(user, 'usuario', 'Usuario@123')
check(status == 403 and 'administrador' in html and 'Fatal error' not in html, 'usuário comum recebe orientação sem erro SQL')
status, url, html = request(user, '/atualizar-banco.php', {'backup_confirmado': '1'})
check(status == 403, 'usuário comum não pode migrar por POST')
admin = client()
status, url, html = login(admin, 'admin', 'Admin@123')
check(status == 200 and url.endswith('/atualizar-banco.php') and 'Atualizar banco' in html, 'admin encaminhado à atualização após login')
token = csrf(html)
status, url, html = request(admin, '/atualizar-banco.php', {'csrf_token': 'invalido', 'backup_confirmado': '1'})
check(status == 403, 'CSRF inválido impede atualização')
status, url, html = request(admin, '/atualizar-banco.php', {'csrf_token': token})
check(status == 422 and 'backup' in html, 'confirmação do backup é obrigatória')
status, url, html = request(admin, '/api/estoques.php')
check(status == 503, 'tentativas negadas não executam migração')
status, url, html = request(admin, '/atualizar-banco.php', {'csrf_token': token, 'backup_confirmado': '1'})
check(status == 200 and 'Atualização concluída' in html, 'atualização pelo navegador concluída')
for path in ['/app/views/dashboard/painel.php', '/app/views/estoques/listar.php', '/app/views/sensores/listar_sensores.php', '/app/views/graficos/alerta.php', '/app/views/graficos/graficos_sensores.php', '/app/views/relatorios/relatorio_qualidade.php', '/app/views/usuarios/gestao_usuarios.php']:
    status, url, html = request(admin, path)
    check(status == 200 and path in url and 'Fatal error' not in html and 'SQLSTATE' not in html, 'tela carregada: ' + path)
status, url, html = request(admin, '/atualizar-banco.php')
check(status == 200 and 'estrutura do banco está pronta' in html and 'name="backup_confirmado"' not in html, 'reabertura reconhece banco atualizado')
status, url, html = request(user, '/app/views/dashboard/painel.php')
check(status == 200 and 'Estoque inicial' in html, 'acesso do usuário legado preservado')
print(f'{checks} verificações de atualização passaram.')
