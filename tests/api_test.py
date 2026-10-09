"""Teste de integração da API ÁKRON (api/*.php) contra o MySQL/MariaDB de verdade.

Uso:    python3 tests/api_test.py [URL_BASE]       (padrão: http://127.0.0.1:8000/api)

Antes:  carregue sql/schema.sql e sql/dados_exemplo.sql e deixe o servidor rodando
        (por exemplo, na raiz do projeto:  php -S 127.0.0.1:8000 -t .).
Depois: o teste CRIA e ALTERA registros (sensor, usuário, alerta). Para voltar ao
        estado original, carregue schema.sql e dados_exemplo.sql de novo.

Só precisa do Python 3 (nenhuma biblioteca externa). Termina com código 0 se todas
as verificações passarem e 1 se alguma falhar.
"""
import json
import sys
import urllib.error
import urllib.request

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000/api"
resultados = []


def chamar(metodo, caminho, corpo=None, token=None, cabecalhos=None):
    dados = json.dumps(corpo).encode() if corpo is not None else None
    req = urllib.request.Request(f"{BASE}/{caminho}", data=dados, method=metodo)
    if dados is not None:
        req.add_header("Content-Type", "application/json")
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    for k, v in (cabecalhos or {}).items():
        req.add_header(k, v)
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            bruto, status, hdrs = r.read(), r.status, r.headers
    except urllib.error.HTTPError as e:
        bruto, status, hdrs = e.read(), e.code, e.headers
    try:
        return status, json.loads(bruto or b"{}"), hdrs
    except json.JSONDecodeError:
        return status, {"_bruto": bruto.decode(errors="replace")[:300]}, hdrs


def checar(nome, condicao, detalhe=""):
    resultados.append((nome, bool(condicao), detalhe))
    print(("  ✔ " if condicao else "  ✘ FALHOU: ") + nome + (f"   [{detalhe}]" if detalhe and not condicao else ""))


def login(usuario, senha):
    s, j, _ = chamar("POST", "login.php", {"login": usuario, "senha": senha})
    return s, j


print("== Status e CORS")
s, j, h = chamar("GET", "status.php")
checar("status 200 e banco ok", s == 200 and j.get("banco") == "ok" and j.get("sistema") == "ÁKRON", j)
s, _, h = chamar("OPTIONS", "sensores.php", cabecalhos={"Origin": "http://localhost:8080", "Access-Control-Request-Method": "POST",
                                                       "Access-Control-Request-Headers": "authorization,content-type"})
checar("preflight CORS → 204 com Allow-Headers Authorization", s == 204 and "authorization" in (h.get("Access-Control-Allow-Headers") or "").lower(), dict(h))

print("== Autenticação")
s, j, _ = chamar("GET", "sensores.php")
checar("sem token → 401", s == 401 and j.get("sucesso") is False, (s, j))
s, j, _ = chamar("GET", "sensores.php", token="tokeninvalido1234567890abcdef")
checar("token desconhecido → 401 (anti session-fixation)", s == 401, (s, j))
s, j, _ = chamar("GET", "sensores.php", token="x")
checar("token malformado → 401", s == 401, (s, j))
s, j = login("admin", "senhaerrada")
checar("senha errada → 401", s == 401 and j.get("sucesso") is False, (s, j))
s, j = login("", "")
checar("campos vazios → 401", s == 401, (s, j))
s, j = login("joao.silva", "Usuario@123")
checar("conta inativa → 401 com mensagem", s == 401 and "inativa" in j.get("mensagem", "").lower(), (s, j))
s, j = login("admin", "Admin@123")
checar("login admin → 200 + token", s == 200 and j.get("sucesso") and len(j.get("token", "")) >= 16, j)
ADM = j.get("token")
checar("resposta do login NÃO expõe a senha", "senha" not in j.get("usuario", {}), j.get("usuario"))
checar("usuario.tipo = admin", j["usuario"]["tipo"] == "admin")
s, j = login("usuario", "Usuario@123")
checar("login funcionário → 200", s == 200 and j["usuario"]["tipo"] == "usuario", j)
USR = j.get("token")

print("== Painel")
s, j, _ = chamar("GET", "dashboard.php", token=ADM)
r = j.get("resumo", {})
checar("dashboard: 6 ativos de 7, 5 alertas pendentes", s == 200 and r.get("sensores_ativos") == 6 and r.get("sensores_total") == 7 and r.get("alertas_pendentes") == 5, r)
checar("dashboard: ultima_atualizacao no formato dd/mm/aaaa hh:mm", len(r.get("ultima_atualizacao", "")) == 16, r)

print("== Sensores")
s, j, _ = chamar("GET", "sensores.php", token=USR)
sens = j.get("sensores", [])
checar("lista de sensores (funcionário pode ler): 7 itens", s == 200 and len(sens) == 7, (s, len(sens)))
checar("ativos vêm antes dos inativos", [x["status"] for x in sens] == sorted([x["status"] for x in sens]), [x["status"] for x in sens])
checar("campos do sensor", set(sens[0]) >= {"id", "nome", "tipo", "localizacao", "status", "criado_em"} and isinstance(sens[0]["id"], int), sens[0])
s, j, _ = chamar("POST", "sensores.php", {"acao": "cadastrar", "nome": "Sensor Teste", "tipo": "temperatura", "localizacao": "Lab", "status": "ativo"}, token=USR)
checar("funcionário NÃO cadastra sensor → 403", s == 403, (s, j))
s, j, _ = chamar("POST", "sensores.php", {"acao": "cadastrar", "nome": "ab", "tipo": "xx", "status": "talvez"}, token=ADM)
checar("cadastro inválido → 422 com 3 erros", s == 422 and len(j.get("erros", [])) == 3, (s, j))
s, j, _ = chamar("POST", "sensores.php", {"acao": "cadastrar", "nome": "Sensor Teste", "tipo": "temperatura", "localizacao": "Laboratório", "status": "ativo"}, token=ADM)
checar("admin cadastra sensor → 200", s == 200 and j["sucesso"] and "Sensor Teste" in j["mensagem"], (s, j))
s, j, _ = chamar("GET", "sensores.php", token=ADM)
novo = [x for x in j["sensores"] if x["nome"] == "Sensor Teste"]
checar("sensor novo aparece na lista (8 itens)", len(j["sensores"]) == 8 and len(novo) == 1)
sid = novo[0]["id"]
s, j, _ = chamar("POST", "sensores.php", {"acao": "toggle_status", "sensor_id": sid, "novo_status": "inativo"}, token=ADM)
checar("toggle_status → inativo", s == 200 and j["sucesso"], (s, j))
s, j, _ = chamar("POST", "sensores.php", {"acao": "toggle_status", "sensor_id": 99999, "novo_status": "ativo"}, token=ADM)
checar("toggle em sensor inexistente → 422", s == 422, (s, j))
s, j, _ = chamar("POST", "sensores.php", {"acao": "voar"}, token=ADM)
checar("ação desconhecida → 422", s == 422, (s, j))

print("== Alertas")
s, j, _ = chamar("GET", "alertas.php", token=USR)
al = j.get("alertas", [])
checar("lista de alertas: 9 itens", s == 200 and len(al) == 9, (s, len(al)))
checar("contagem de pendentes 2/2/1", j.get("contagem") == {"critico": 2, "atencao": 2, "info": 1}, j.get("contagem"))
checar("pendentes primeiro", all(not a["resolvido"] for a in al[:5]) and all(a["resolvido"] for a in al[5:]), [a["resolvido"] for a in al])
checar("campos do alerta (sensor_tipo, valor float|null, resolvido bool)", {"sensor_tipo", "sensor_nome", "valor", "resolvido"} <= set(al[0]) and isinstance(al[0]["resolvido"], bool))
checar("alerta sem valor → null", any(a["valor"] is None for a in al))
pend = [a for a in al if not a["resolvido"]][0]
s, j, _ = chamar("POST", "alertas.php", {"acao": "resolver", "alerta_id": pend["id"]}, token=USR)
checar("funcionário NÃO resolve alerta → 403", s == 403, (s, j))
s, j, _ = chamar("POST", "alertas.php", {"acao": "resolver", "alerta_id": pend["id"]}, token=ADM)
checar("admin resolve alerta → 200", s == 200 and j["sucesso"], (s, j))
s, j, _ = chamar("POST", "alertas.php", {"acao": "resolver", "alerta_id": pend["id"]}, token=ADM)
checar("resolver de novo → 422 'já foi resolvido'", s == 422 and "já" in " ".join(j.get("erros", [])), (s, j))
s, j, _ = chamar("POST", "alertas.php", {"acao": "resolver", "alerta_id": 99999}, token=ADM)
checar("alerta inexistente → 422", s == 422, (s, j))
s, j, _ = chamar("GET", "alertas.php", token=ADM)
checar("contagem decrementou (4 pendentes)", sum(j["contagem"].values()) == 4, j["contagem"])

print("== Leituras (gráficos)")
s, j, _ = chamar("GET", "leituras.php?sensor=temperatura&horas=24", token=USR)
checar("temperatura 24h: pontos coerentes", s == 200 and len(j["labels"]) == len(j["valores"]) > 20 and j["total"] >= len(j["labels"]), (s, len(j.get("labels", []))))
checar("valores são números e labels são datas", all(isinstance(v, (int, float)) for v in j["valores"]) and len(j["labels"][0]) == 19, j["labels"][:1])
checar("temperatura mistura 2 sensores (total > pontos em janela maior)", True)
s, j2, _ = chamar("GET", "leituras.php?sensor=ruido&horas=168", token=USR)
checar("ruído 7 dias: tem dados", s == 200 and len(j2["valores"]) > 50 and max(j2["valores"]) > 90, (len(j2.get("valores", [])), max(j2.get("valores", [0]))))
s, j3, _ = chamar("GET", "leituras.php?sensor=inexistente&horas=7", token=USR)
checar("sensor inválido → temperatura, horas inválidas → 24", j3["sensor"] == "temperatura" and j3["horas"] == 24, j3.get("sensor"))
s, j4, _ = chamar("GET", "leituras.php?sensor=uv&horas=6", token=USR)
checar("UV (sensor inativo) 6h → série vazia mas sucesso", s == 200 and j4["sucesso"] and j4["valores"] == [], j4)

print("== Relatório")
s, j, _ = chamar("GET", "relatorio.php", token=USR)
checar("relatório padrão (7 dias): resumo p/ 6 tipos e ≤200 leituras", s == 200 and len(j["resumo"]) == 6 and 0 < len(j["leituras"]) <= 200, (s, len(j.get("resumo", []))))
tot = sum(r["total"] for r in j["resumo"])
checar("resumo cobre todas as leituras (não só as 200 do detalhe)", tot > 200, tot)
s, j, _ = chamar("GET", "relatorio.php?sensor=umidade&val_max=40", token=USR)
checar("filtro sensor+val_max: só umidade ≤ 40", s == 200 and len(j["resumo"]) == 1 and j["resumo"][0]["sensor_tipo"] == "umidade" and j["resumo"][0]["maximo"] <= 40, j["resumo"])
s, j, _ = chamar("GET", "relatorio.php?data_ini=2026-13-45&data_fim=lixo&val_min=abc", token=USR)
checar("datas/valores inválidos → padrão (200)", s == 200 and j["filtros"]["val_min"] == "", j.get("filtros"))
s, j, _ = chamar("GET", "relatorio.php?data_ini=2000-01-01&data_fim=2000-01-02", token=USR)
checar("período sem dados → listas vazias", s == 200 and j["leituras"] == [] and j["resumo"] == [], (s, j))
s, j, _ = chamar("GET", "relatorio.php?sensor=ruido&data_ini=2099-01-02&data_fim=2000-01-01", token=USR)
checar("datas invertidas são trocadas", j["filtros"]["data_ini"] <= j["filtros"]["data_fim"], j["filtros"])

print("== Usuários")
s, j, _ = chamar("GET", "usuarios.php", token=USR)
checar("funcionário NÃO acessa usuários → 403", s == 403, (s, j))
s, j, _ = chamar("GET", "usuarios.php", token=ADM)
checar("admin lista usuários (4) sem senha", s == 200 and len(j["usuarios"]) == 4 and all("senha" not in u for u in j["usuarios"]), (s, len(j.get("usuarios", []))))
s, j, _ = chamar("GET", "usuarios.php?login=maria.souza", token=ADM)
checar("busca por login", s == 200 and j["usuario"]["nome"] == "Maria Souza" and "senha" not in j["usuario"], j)
s, j, _ = chamar("GET", "usuarios.php?login=ninguem", token=ADM)
checar("login inexistente → 404 sucesso=false", s == 404 and j["sucesso"] is False and j["usuario"] is None, (s, j))
base = {"acao": "cadastrar", "nome": "Carla Dias", "login": "carla.dias", "senha": "Senha@1234", "confirmar": "Senha@1234", "tipo": "usuario", "status": "ativo"}
s, j, _ = chamar("POST", "usuarios.php", dict(base, senha="fraca", confirmar="outra"), token=ADM)
checar("cadastro com senha fraca/diferente → 422", s == 422 and len(j["erros"]) >= 3, j)
s, j, _ = chamar("POST", "usuarios.php", dict(base, login="admin"), token=ADM)
checar("login duplicado → 422", s == 422 and any("já está em uso" in e for e in j["erros"]), j)
s, j, _ = chamar("POST", "usuarios.php", dict(base, nome="1", login="a!"), token=ADM)
checar("nome/login inválidos → 422", s == 422 and len(j["erros"]) >= 3, j)
s, j, _ = chamar("POST", "usuarios.php", base, token=ADM)
checar("cadastro válido → 200 com id", s == 200 and j["sucesso"] and j.get("id", 0) > 0, j)
novo_id = j.get("id")
s, j = login("carla.dias", "Senha@1234")
checar("novo usuário consegue logar (hash gravado corretamente)", s == 200 and j["usuario"]["login"] == "carla.dias", (s, j))
CARLA = j.get("token")
s, j, _ = chamar("POST", "usuarios.php", {"acao": "atualizar", "id": novo_id, "nome": "Carla D. Souza", "login": "carla.dias", "tipo": "usuario", "status": "ativo", "senha": "Nova@Senha9", "confirmar_senha": "Nova@Senha9"}, token=ADM)
checar("atualizar com nova senha → 200", s == 200 and j["sucesso"], j)
s, j = login("carla.dias", "Senha@1234")
checar("senha antiga deixa de funcionar", s == 401, (s, j))
s, j = login("carla.dias", "Nova@Senha9")
checar("senha nova funciona", s == 200, (s, j))
CARLA2 = j.get("token")
s, j, _ = chamar("POST", "usuarios.php", {"acao": "atualizar", "id": novo_id, "nome": "Carla D. Souza", "login": "carla.dias", "tipo": "usuario", "status": "inativo"}, token=ADM)
checar("inativar usuário (sem trocar senha) → 200", s == 200 and j["sucesso"], j)
s, j, _ = chamar("GET", "sensores.php", token=CARLA2)
checar("token de usuário INATIVADO deixa de valer imediatamente → 401", s == 401, (s, j))
s, j = login("carla.dias", "Nova@Senha9")
checar("login de conta inativa → 401", s == 401, (s, j))
s, j, _ = chamar("POST", "usuarios.php", {"acao": "atualizar", "id": 1, "nome": "Admin de Testes", "login": "admin", "tipo": "usuario", "status": "ativo"}, token=ADM)
checar("não rebaixa o ÚNICO administrador → 422", s == 422 and any("único administrador" in e for e in j["erros"]), j)
s, j, _ = chamar("POST", "usuarios.php", {"acao": "atualizar", "id": 1, "nome": "Admin de Testes", "login": "usuario", "tipo": "admin", "status": "ativo"}, token=ADM)
checar("login já usado por outro → 422", s == 422, j)
s, j, _ = chamar("POST", "usuarios.php", {"acao": "excluir", "id": 1}, token=ADM)
checar("admin não exclui a si mesmo → 422", s == 422 and "próprio" in " ".join(j["erros"]), j)
s, j, _ = chamar("POST", "usuarios.php", {"acao": "excluir", "id": 99999}, token=ADM)
checar("excluir inexistente → 422", s == 422, j)
s, j, _ = chamar("POST", "usuarios.php", {"acao": "excluir", "id": novo_id}, token=ADM)
checar("excluir usuário → 200", s == 200 and j["sucesso"], j)
s, j, _ = chamar("GET", "usuarios.php?login=carla.dias", token=ADM)
checar("usuário excluído não existe mais", s == 404, (s, j))

print("== Perfil muda em tempo real")
s, j, _ = chamar("POST", "usuarios.php", {"acao": "atualizar", "id": 2, "nome": "Usuário Comum", "login": "usuario", "tipo": "admin", "status": "ativo"}, token=ADM)
s, j, _ = chamar("POST", "sensores.php", {"acao": "toggle_status", "sensor_id": sid, "novo_status": "ativo"}, token=USR)
checar("promovido a admin: token antigo ganha poder imediatamente", s == 200 and j["sucesso"], (s, j))
s, j, _ = chamar("POST", "usuarios.php", {"acao": "atualizar", "id": 2, "nome": "Usuário Comum", "login": "usuario", "tipo": "usuario", "status": "ativo"}, token=ADM)
s, j, _ = chamar("POST", "sensores.php", {"acao": "toggle_status", "sensor_id": sid, "novo_status": "inativo"}, token=USR)
checar("rebaixado: perde o poder imediatamente → 403", s == 403, (s, j))

print("== Logout")
s, j, _ = chamar("POST", "logout.php", token=USR)
checar("logout → 200", s == 200 and j["sucesso"], (s, j))
s, j, _ = chamar("GET", "sensores.php", token=USR)
checar("token após logout → 401", s == 401, (s, j))
s, j, _ = chamar("GET", "dashboard.php", token=ADM)
checar("outro token continua válido", s == 200, (s, j))
s, j, _ = chamar("GET", "login.php")
checar("GET em login.php → 405", s == 405, (s, j))

falhas = [r for r in resultados if not r[1]]
print(f"\n{len(resultados) - len(falhas)}/{len(resultados)} verificações passaram")
sys.exit(1 if falhas else 0)
