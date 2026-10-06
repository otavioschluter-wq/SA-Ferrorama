"""Teste HTTP no banco isolado, com servidor PHP local iniciado separadamente.

Requer ATRAIN_TEST_PASSWORD e contas admin_bloco2, gestor_bloco2,
maq_bloco2, sem_trem_bloco2 com essa senha no banco de teste.
"""
import os
import re
import uuid

import requests

BASE = os.getenv("ATRAIN_TEST_URL", "http://127.0.0.1:8765")
PASSWORD = os.environ["ATRAIN_TEST_PASSWORD"]


def check(condition, message):
    if not condition:
        raise AssertionError(message)
    print("OK", message)


def csrf(html):
    match = re.search(r'name="csrf" value="([a-f0-9]{64})"', html)
    check(match is not None, "token CSRF presente")
    return match.group(1)


def login(name):
    session = requests.Session()
    response = session.post(BASE + "/index.php", data={"login": name, "senha": PASSWORD}, allow_redirects=False)
    check(response.status_code == 302 and response.headers.get("Location") == "principal.php", f"login {name}")
    return session


def get(session, path):
    return session.get(BASE + path, allow_redirects=False)


def post(session, path, data):
    return session.post(BASE + path, data=data, allow_redirects=False)


anonymous = requests.Session()
for page in ["/principal.php", "/trens.php", "/trens_form.php", "/sensores.php", "/sensores_form.php", "/usuarios/cadastrar.php", "/usuarios/atribuir_trem.php"]:
    response = get(anonymous, page)
    check(response.status_code == 302 and "index.php" in response.headers.get("Location", ""), f"anônimo bloqueado {page}")

admin = login("admin_bloco2")
for page in ["/principal.php", "/trens.php", "/sensores.php", "/usuarios/cadastrar.php"]:
    response = get(admin, page)
    check(response.status_code == 200 and "Admin Teste" in response.text and "no-store" in response.headers.get("Cache-Control", ""), f"sessão persistente {page}")

check(get(admin, "/sair.php").status_code == 405, "GET não encerra sessão")
check(get(admin, "/principal.php").status_code == 200, "sessão mantém-se após GET sair")
check(post(admin, "/sair.php", {}).status_code == 403, "logout sem CSRF bloqueado")
check(post(admin, "/trens.php", {"excluir_id": 1}).status_code == 403, "exclusão de trem sem CSRF bloqueada")
check(post(admin, "/sensores.php", {"excluir_id": 1}).status_code == 403, "exclusão de sensor sem CSRF bloqueada")
check(post(admin, "/trens_form.php", {"prefixo": "TR-999"}).status_code == 403, "cadastro de trem sem CSRF bloqueado")
check(post(admin, "/sensores_form.php", {"codigo": "S-TEMP-999"}).status_code == 403, "cadastro de sensor sem CSRF bloqueado")

new_login = "publico_" + uuid.uuid4().hex[:8]
registration = post(anonymous, "/usuarios/registrar.php", {"nome":"Novo Maquinista", "login":new_login, "senha":PASSWORD, "confirmacao":PASSWORD, "papel":"administrador"})
check(registration.status_code == 302 and "index.php" in registration.headers.get("Location", ""), "cadastro público anterior funciona")
public = login(new_login)
check(get(public, "/usuarios/cadastrar.php").status_code == 403, "cadastro público não cria administrador")
check("Nenhum trem atribuído" in get(public, "/sensores.php").text, "cadastro público inicia sem acesso a todos os sensores")
assign_html = get(admin, "/usuarios/atribuir_trem.php").text
public_id = re.search(r'<option value="(\d+)">Novo Maquinista \(' + new_login, assign_html).group(1)
assign_token = csrf(assign_html)
check("Selecione um trem existente" in post(admin, "/usuarios/atribuir_trem.php", {"csrf":assign_token, "id_usuario":public_id, "id_trem":"999999"}).text, "atribuição valida trem")
check("Atribuição atualizada" in post(admin, "/usuarios/atribuir_trem.php", {"csrf":assign_token, "id_usuario":public_id, "id_trem":"1"}).text, "administrador atribui trem existente")
admin_created = "criado_" + uuid.uuid4().hex[:8]
admin_form = csrf(get(admin, "/usuarios/cadastrar.php").text)
created = post(admin, "/usuarios/cadastrar.php", {"csrf":admin_form, "nome":"Gestor Criado", "login":admin_created, "papel":"gestor", "senha":PASSWORD, "confirmacao":PASSWORD, "trem_atribuido_id":""})
check(created.status_code == 200 and "Usuário cadastrado com sucesso" in created.text, "cadastro administrativo anterior funciona")
created_session = login(admin_created)
check(get(created_session, "/trens.php").status_code == 200, "papel criado pelo administrador funciona")

suffix = uuid.uuid4().hex[:3].upper()
prefix = "TR-" + str(int(suffix, 16) % 800 + 100)
while prefix in get(admin, "/trens.php").text:
    prefix = "TR-" + str((int(prefix[-3:]) % 800) + 100)
train_form = csrf(get(admin, "/trens_form.php").text)
train = {"csrf": train_form, "id_trem": "", "prefixo": prefix, "modelo": "GE ES43BBi Teste", "ano": "2020", "status": "normal", "capacidade_toneladas": "125.50", "ultima_inspecao": "2026-09-01"}
check("padrão TR-204" in post(admin, "/trens_form.php", {**train, "prefixo": "X"}).text, "prefixo inválido")
check("capacidade positiva" in post(admin, "/trens_form.php", {**train, "capacidade_toneladas": "-1"}).text, "capacidade inválida")
check(post(admin, "/trens_form.php", train).status_code == 303, "cadastrar trem")
check(prefix in get(admin, "/trens.php?busca=" + prefix + "&status=normal").text, "buscar e filtrar trem")
check("já está cadastrado" in post(admin, "/trens_form.php", train).text, "prefixo duplicado")

list_html = get(admin, "/trens.php?busca=" + prefix).text
train_id = re.search(r'trens_form.php\?id=(\d+)', list_html).group(1)
check(post(admin, "/trens_form.php", {**train, "id_trem": train_id, "modelo": "GE Atualizado", "status": "atencao"}).status_code == 303, "editar trem")
check("GE Atualizado" in get(admin, "/trens.php?busca=" + prefix + "&status=atencao").text, "edição persistida")

sensor_form = csrf(get(admin, "/sensores_form.php").text)
sensor_code = "S-TEMP-" + prefix[-3:]
sensor = {"csrf": sensor_form, "id_sensor": "", "id_trem": train_id, "codigo": sensor_code, "tipo": "temperatura", "localizacao": "Motor", "segmento": "Trecho Norte", "ultima_leitura": "normal"}
check("não existe" in post(admin, "/sensores_form.php", {**sensor, "id_trem": "999999"}).text, "trem inexistente")
check(post(admin, "/sensores_form.php", sensor).status_code == 303, "cadastrar sensor")
check(sensor_code in get(admin, "/sensores.php?tipo=temperatura").text and prefix in get(admin, "/sensores.php?tipo=temperatura").text, "filtro por tipo e JOIN com prefixo")
check(sensor_code not in get(admin, "/sensores.php?tipo=energia").text, "filtro exclui outro tipo")
sensor_id = re.search(r'sensores_form.php\?id=(\d+)', get(admin, "/sensores.php?tipo=temperatura").text.split(sensor_code)[1]).group(1)
check(post(admin, "/sensores_form.php", {**sensor, "id_sensor": sensor_id, "ultima_leitura": "critico"}).status_code == 303, "editar sensor")
check("estado-critico" in get(admin, "/sensores.php?tipo=temperatura").text, "indicador editado")
check("possui sensores" in post(admin, "/trens.php", {"csrf": train_form, "excluir_id": train_id}).text, "exclusão de trem com sensor bloqueada")
check("Sensor excluído" in post(admin, "/sensores.php", {"csrf": sensor_form, "excluir_id": sensor_id}).text, "excluir sensor")
check("Trem excluído" in post(admin, "/trens.php", {"csrf": train_form, "excluir_id": train_id}).text, "excluir trem")

gestor = login("gestor_bloco2")
check(get(gestor, "/trens.php").status_code == 200 and get(gestor, "/sensores_form.php").status_code == 200, "gestor gere trens e sensores")
check(get(gestor, "/usuarios/cadastrar.php").status_code == 403, "gestor não cadastra usuários")
maq = login("maq_bloco2")
check(get(maq, "/trens.php").status_code == 403 and get(maq, "/trens_form.php").status_code == 403, "maquinista sem gestão de trens")
check(get(maq, "/sensores_form.php").status_code == 403 and post(maq, "/sensores.php", {"excluir_id": 1}).status_code == 403, "maquinista sem mutação de sensores")
check(get(maq, "/sensores.php").status_code == 200, "maquinista consulta sensores")
check("S-TEMP-001" in get(maq, "/sensores.php").text and "S-VELO-002" not in get(maq, "/sensores.php").text, "maquinista vê somente sensores do trem atribuído")
check(get(maq, "/usuarios/atribuir_trem.php").status_code == 403, "maquinista sem atribuição de trem")
check("S-TEMP-001" in get(public, "/sensores.php").text and "S-VELO-002" not in get(public, "/sensores.php").text, "nova atribuição aplica-se sem novo login")
sem_trem = login("sem_trem_bloco2")
check("Nenhum trem atribuído" in get(sem_trem, "/sensores.php").text, "sem trem não vê todos os sensores")

token = csrf(get(admin, "/principal.php").text)
check(post(admin, "/sair.php", {"csrf": token}).status_code == 303, "logout POST")
for page in ["/principal.php", "/trens.php", "/sensores.php"]:
    check(get(admin, page).status_code == 302, f"voltar/recarregar bloqueado {page}")
print("Todos os testes HTTP passaram.")
