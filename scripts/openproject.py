#!/usr/bin/env python3
"""Puente entre el vault del LMS (docs/) y OpenProject (API v3).

Uso:
  python scripts/openproject.py cargar [--dry-run]
  python scripts/openproject.py estado <OP_ID> "<estado>"
  python scripts/openproject.py comentar <OP_ID> "<texto>"
  python scripts/openproject.py bug --rf RF20 --titulo "..." --severidad Alta [--descripcion "..."]

Variables de entorno:
  OP_URL       p. ej. https://openproject.midominio.com
  OP_TOKEN     token de API (Mi cuenta → Tokens de acceso → API)
  OP_PROYECTO  identificador del proyecto, p. ej. lms-ues
  LMS_VAULT    ruta del vault (por defecto: docs)

`cargar` es idempotente: busca por asunto y solo crea lo que falta.
`cargar --dry-run` sin OP_URL muestra el plan leído del vault sin conectarse.
"""
import argparse
import base64
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

HITOS = {
    "H0": ("H0 Base", "2026-10-12", "2026-10-16"),
    "H1": ("H1 Núcleo", "2026-10-19", "2026-10-30"),
    "H2": ("H2 Flujos", "2026-11-03", "2026-11-11"),
    "H3": ("H3 Integración", "2026-11-12", "2026-11-17"),
    "H4": ("H4 Estabilización", "2026-11-18", "2026-11-24"),
}
HITOS_FECHA = [("Congelado del desarrollo", "2026-11-18"), ("Entrega LMS", "2026-11-25")]

MODULO_EQUIPO = {
    "Identidad y acceso": "Equipo 1", "Auditoría": "Equipo 1", "Dashboards y reportes": "Equipo 1",
    "Gestión académica": "Equipo 2",
    "Contenidos": "Equipo 3", "Comunicación y notificaciones": "Equipo 3",
    "Actividades y evaluaciones": "Equipo 4", "Calificaciones y progreso": "Equipo 4",
    "Asistencia": "Equipo 5",
}

BASE_TAREAS = [
    ("Esqueleto Laravel 12 + app/Modules + plantilla del repositorio", "Equipo 1"),
    ("Componentes UI Tailwind: layout, botón, modal, acordeón, tabla, formulario", "Equipo 1"),
    ("Migraciones y factories núcleo: users, carreras, planes, periodos, asignaturas", "Equipo 1"),
    ("Migraciones y factories núcleo: secciones, horarios, computos, matriculas, actividades", "Equipo 2"),
    ("Roles y permisos sembrados según la Matriz RBAC", "Equipo 1"),
    ("Seeders de desarrollo + inicio de sesión de desarrollo", "Equipo 1"),
    ("CI en GitHub Actions: Pint, Larastan, Pest, Browser", "Equipo 1"),
    ("Laravel Boost + directrices de IA + vault en docs/", "Equipo 1"),
]

# Trabajo transversal: cada equipo aporta a los paneles y reportes de su módulo.
EXTRA = {
    "RF24": [(f"RF24 · Widgets del panel · Equipo {n}", f"Equipo {n}") for n in (2, 3, 4, 5)],
    "RF25": [(f"RF25 · Reportes · Equipo {n}", f"Equipo {n}") for n in (1, 3, 4, 5)],
}

PRIORIDAD = {"Alta": ["High", "Alta"], "Media-Alta": ["High", "Alta"], "Media": ["Normal"], "Baja": ["Low", "Baja"]}
SEVERIDAD = {"Crítica": ["Immediate", "Inmediata"], "Alta": ["High", "Alta"], "Media": ["Normal"], "Baja": ["Low", "Baja"]}
FASE_RAIZ = "Fase 4: Desarrollo del sistema"
TIPOS = {"resumen": ["Summary task", "Tarea resumen"], "epica": ["Epic", "Épica"], "feature": ["Feature", "Funcionalidad"], "tarea": ["Task", "Tarea"],
         "bug": ["Bug", "Error"], "hito": ["Milestone", "Hito"]}


# ---------- Vault ----------
def frontmatter(texto):
    m = re.match(r"^---\n(.*?)\n---\n", texto, re.S)
    datos = {}
    for linea in (m.group(1).splitlines() if m else []):
        k, _, v = linea.partition(":")
        v = v.strip()
        if v.startswith("["):
            try:
                v = [re.sub(r"^\[\[|\]\]$", "", x) for x in json.loads(v)]
            except json.JSONDecodeError:
                pass
        elif v.startswith('"') and v.endswith('"'):
            v = re.sub(r"^\[\[|\]\]$", "", v[1:-1])
        datos[k.strip()] = v
    return datos


def seccion(texto, titulo):
    m = re.search(rf"^## {re.escape(titulo)}\n(.*?)(?=^## |\Z)", texto, re.S | re.M)
    return m.group(1).strip() if m else ""


def leer_vault(vault):
    rfs = []
    for f in sorted((vault / "03 Requisitos" / "Funcionales").glob("RF*.md")):
        t = f.read_text(encoding="utf-8")
        fm = frontmatter(t)
        if fm.get("estado") == "descartado" or not fm.get("equipo"):
            continue
        cp = vault / "10 Calidad" / "Casos de prueba" / f"CP-{f.stem}.md"
        casos = re.findall(r"^\| (CP-RF\d+-\d+) \|", cp.read_text(encoding="utf-8"), re.M) if cp.exists() else []
        rfs.append({**fm, "descripcion": seccion(t, "Descripción"), "criterios": seccion(t, "Criterios de aceptación"),
                    "casos": casos, "ruta": f"docs/03 Requisitos/Funcionales/{f.name}"})
    return rfs


# ---------- API ----------
class OP:
    def __init__(self, url, token, proyecto, dry):
        self.url, self.dry = url.rstrip("/"), dry
        self.auth = "Basic " + base64.b64encode(f"apikey:{token}".encode()).decode()
        self.proyecto = self.get(f"/api/v3/projects/{proyecto}")
        self.pid = self.proyecto["id"]

    def req(self, metodo, ruta, cuerpo=None):
        data = json.dumps(cuerpo).encode() if cuerpo is not None else None
        r = urllib.request.Request(self.url + ruta, data=data, method=metodo,
                                   headers={"Authorization": self.auth, "Content-Type": "application/json",
                                            # Cloudflare (error 1010) bloquea el User-Agent por defecto de urllib
                                            "User-Agent": "lms-ues-openproject/1.0", "Accept": "application/hal+json"})
        try:
            with urllib.request.urlopen(r) as resp:
                return json.loads(resp.read() or b"{}")
        except urllib.error.HTTPError as e:
            sys.exit(f"ERROR {metodo} {ruta}: {e.code} {e.read().decode()[:500]}")

    def get(self, ruta):
        return self.req("GET", ruta)

    def post(self, ruta, cuerpo, etiqueta):
        if self.dry:
            print(f"  [dry-run] crear {etiqueta}")
            return {"id": f"nuevo:{etiqueta}", "_links": {}}
        print(f"  + {etiqueta}")
        return self.req("POST", ruta, cuerpo)

    def elementos(self, ruta):
        out, offset = [], 1
        while True:
            sep = "&" if "?" in ruta else "?"
            pag = self.get(f"{ruta}{sep}pageSize=200&offset={offset}")
            out += pag["_embedded"]["elements"]
            if len(out) >= pag.get("total", 0) or not pag["_embedded"]["elements"]:
                return out
            offset += 1

    def por_nombre(self, elementos, candidatos):
        for c in candidatos:
            for e in elementos:
                if e.get("name", "").lower() == c.lower():
                    return e
        return None


def campos_personalizados(op, tipo_id):
    esquema = op.get(f"/api/v3/work_packages/schemas/{op.pid}-{tipo_id}")
    cf = {}
    for k, v in esquema.items():
        if k.startswith("customField") and isinstance(v, dict):
            opciones = {o["title"]: o["href"] for o in v.get("_links", {}).get("allowedValues", []) if "title" in o}
            cf[v.get("name")] = (k, opciones)
    return cf


def aplicar_cf(cuerpo, cf, nombre, valor):
    if nombre not in cf or valor is None:
        return
    clave, opciones = cf[nombre]
    if opciones:
        if valor in opciones:
            cuerpo["_links"][clave] = {"href": opciones[valor]}
        else:
            print(f"  ! «{valor}» no es una opción del campo {nombre}")
    else:
        cuerpo[clave] = valor


# ---------- Comandos ----------
def cargar(args):
    vault = Path(os.environ.get("LMS_VAULT", "docs"))
    rfs = leer_vault(vault)
    if args.dry_run and not os.environ.get("OP_URL"):
        print(f"Vault: {vault} · {len(rfs)} RF\n")
        for h, (nombre, ini, fin) in HITOS.items():
            print(f"{nombre} ({ini} → {fin})")
            for r in rfs:
                if r["hito"] == h:
                    print(f"  {r['id']} · {r['nombre']}  [{r['equipo']} · {r['modulo']}]  depende: {', '.join(r['depende']) or '—'}  · {len(r['casos'])} CP")
        return

    op = OP(os.environ["OP_URL"], os.environ["OP_TOKEN"], os.environ["OP_PROYECTO"], args.dry_run)
    tipos = op.elementos(f"/api/v3/projects/{op.pid}/types")
    t = {k: op.por_nombre(tipos, v) for k, v in TIPOS.items()}
    faltan = [k for k, v in t.items() if not v]
    if faltan:
        sys.exit(f"Faltan tipos en el proyecto: {faltan}. Actívelos en Configuración del proyecto → Tipos.")
    prioridades = op.elementos("/api/v3/priorities")
    cf = campos_personalizados(op, t["feature"]["id"])
    for n in ("RF", "Módulo", "Equipo"):
        if n not in cf:
            print(f"! Falta el campo personalizado «{n}» (ver la nota OpenProject del vault)")

    # Versiones = hitos
    versiones = {v["name"]: v for v in op.elementos(f"/api/v3/projects/{op.pid}/versions")}
    for h, (nombre, ini, fin) in HITOS.items():
        if nombre not in versiones:
            versiones[nombre] = op.post("/api/v3/versions", {
                "name": nombre, "startDate": ini, "endDate": fin,
                "_links": {"definingProject": {"href": f"/api/v3/projects/{op.pid}"}}}, f"versión {nombre}")

    filtro = urllib.parse.quote(json.dumps([{"status": {"operator": "*", "values": []}}]))
    existentes = {wp["subject"]: wp for wp in op.elementos(f"/api/v3/projects/{op.pid}/work_packages?filters={filtro}")}
    por_prefijo = {s.split(" · ")[0]: wp for s, wp in existentes.items()
                   if wp["_links"]["type"]["href"].endswith(f"/{t['feature']['id']}")}

    def crear(asunto, tipo, hito=None, padre=None, descripcion="", equipo=None, rf=None, modulo=None, prioridad=None, fecha=None):
        if asunto in existentes:
            return existentes[asunto]
        cuerpo = {"subject": asunto, "scheduleManually": True,
                  "description": {"format": "markdown", "raw": descripcion},
                  "_links": {"type": {"href": f"/api/v3/types/{t[tipo]['id']}"}}}
        if hito:
            nombre, ini, fin = HITOS[hito]
            cuerpo.update(startDate=ini, dueDate=fin)
            cuerpo["_links"]["version"] = {"href": f"/api/v3/versions/{versiones[nombre]['id']}"}
        if fecha:
            cuerpo["date"] = fecha
        if padre and not str(padre["id"]).startswith("nuevo:"):
            cuerpo["_links"]["parent"] = {"href": f"/api/v3/work_packages/{padre['id']}"}
        if prioridad:
            p = op.por_nombre(prioridades, PRIORIDAD.get(prioridad, ["Normal"]))
            if p:
                cuerpo["_links"]["priority"] = {"href": p["_links"]["self"]["href"]}
        for nombre_cf, valor in (("Equipo", equipo), ("RF", rf), ("Módulo", modulo)):
            aplicar_cf(cuerpo, cf, nombre_cf, valor)
        meta = " · ".join(f"**{k}:** {v}" for k, v in (("Equipo", equipo), ("Módulo", modulo), ("Hito", HITOS[hito][0] if hito else None)) if v)
        if meta:
            cuerpo["description"]["raw"] = meta + ("\n\n" + descripcion if descripcion else "")
        wp = op.post(f"/api/v3/projects/{op.pid}/work_packages", cuerpo, f"{tipo}: {asunto}")
        existentes[asunto] = wp
        return wp

    print(FASE_RAIZ)
    raiz = crear(FASE_RAIZ, "resumen", descripcion="Desarrollo del LMS según `docs/00 Plan de desarrollo.md`. Entrega: 25/11/2026.")

    print("Hitos de fecha")
    for nombre, fecha in HITOS_FECHA:
        crear(nombre, "hito", padre=raiz, fecha=fecha)

    print("Épicas por módulo")
    epicas = {m: crear(f"Módulo: {m}", "epica", padre=raiz, equipo=e, modulo=m) for m, e in MODULO_EQUIPO.items()}

    print("Base técnica (H0)")
    base = existentes.get("BASE · Base técnica") or crear("BASE · Base técnica", "feature", "H0", padre=raiz, equipo="Equipo 1",
                                                         descripcion="Base común para que los 5 equipos trabajen en paralelo desde el día 1.\n\n**Rama:** `base-h0-tecnica`")
    for asunto, equipo in BASE_TAREAS:
        crear(f"BASE · {asunto}", "tarea", "H0", padre=base, equipo=equipo)

    print("Requisitos")
    features = {"BASE": base}
    for r in rfs:
        desc = (f"**Especificación:** `{r['ruta']}` · **Rama:** `{r.get('rama', '')}`\n\n{r['descripcion']}\n\n### Criterios de aceptación\n{r['criterios']}\n\n"
                f"**Depende de:** {', '.join(r['depende']) or '—'}")
        f = por_prefijo.get(r["id"]) or crear(f"{r['id']} · {r['nombre']}", "feature", r["hito"], epicas.get(r["modulo"]), desc,
                                               r["equipo"], r["id"], r["modulo"], r["prioridad"])
        features[r["id"]] = f
        comun = dict(hito=r["hito"], padre=f, equipo=r["equipo"], rf=r["id"], modulo=r["modulo"])
        crear(f"{r['id']} · Back", "tarea", descripcion="Migraciones, modelos, Services, Policies, jobs (según el plan aprobado).", **comun)
        crear(f"{r['id']} · Front", "tarea", descripcion="Componentes Livewire y vistas Tailwind; textos de usted; accesibilidad AA.", **comun)
        crear(f"{r['id']} · Test", "tarea", descripcion="Pruebas Pest con ID:\n" + "\n".join(f"- [ ] {c}" for c in r["casos"]), **comun)
        for asunto, equipo in EXTRA.get(r["id"], []):
            crear(asunto, "tarea", r["hito"], f, equipo=equipo, rf=r["id"], modulo=r["modulo"])

    print("Dependencias")
    for r in rfs:
        wp = features[r["id"]]
        if str(wp["id"]).startswith("nuevo:"):
            continue
        actuales = set()
        for rel in op.get(f"/api/v3/work_packages/{wp['id']}/relations")["_embedded"]["elements"]:  # leer es seguro en --dry-run
            actuales |= {rel["_links"]["from"]["href"], rel["_links"]["to"]["href"]}
        for dep in ["BASE"] + r["depende"]:
            destino = features.get(dep)
            if not destino or str(destino["id"]).startswith("nuevo:"):
                continue
            href = f"/api/v3/work_packages/{destino['id']}"
            if href not in actuales:
                op.post(f"/api/v3/work_packages/{wp['id']}/relations",
                        {"type": "follows", "_links": {"to": {"href": href}}}, f"{r['id']} sigue a {dep}")
    print("Listo.")


def estado(args):
    op = OP(os.environ["OP_URL"], os.environ["OP_TOKEN"], os.environ["OP_PROYECTO"], False)
    est = op.por_nombre(op.elementos("/api/v3/statuses"), [args.estado])
    if not est:
        sys.exit(f"No existe el estado «{args.estado}»")
    wp = op.get(f"/api/v3/work_packages/{args.op_id}")
    op.req("PATCH", f"/api/v3/work_packages/{args.op_id}",
           {"lockVersion": wp["lockVersion"], "_links": {"status": {"href": est["_links"]["self"]["href"]}}})
    print(f"OP#{args.op_id} → {est['name']}")


def comentar(args):
    op = OP(os.environ["OP_URL"], os.environ["OP_TOKEN"], os.environ["OP_PROYECTO"], False)
    op.req("POST", f"/api/v3/work_packages/{args.op_id}/activities", {"comment": {"raw": args.texto}})
    print(f"Comentario agregado a OP#{args.op_id}")


def bug(args):
    op = OP(os.environ["OP_URL"], os.environ["OP_TOKEN"], os.environ["OP_PROYECTO"], False)
    tipos = op.elementos(f"/api/v3/projects/{op.pid}/types")
    tipo_bug, tipo_feature = op.por_nombre(tipos, TIPOS["bug"]), op.por_nombre(tipos, TIPOS["feature"])
    filtro = urllib.parse.quote(json.dumps([{"subject": {"operator": "~", "values": [f"{args.rf} ·"]}},
                                            {"status": {"operator": "*", "values": []}}]))
    padre = next((w for w in op.elementos(f"/api/v3/projects/{op.pid}/work_packages?filters={filtro}")
                  if w["_links"]["type"]["href"].endswith(f"/{tipo_feature['id']}")), None)
    cuerpo = {"subject": f"BUG · {args.rf} · {args.titulo}",
              "description": {"format": "markdown", "raw": args.descripcion or
                              "**Entorno:**\n**Rol:**\n**Pasos:**\n1.\n**Esperado:**\n**Obtenido:**\n**Evidencia:**"},
              "_links": {"type": {"href": f"/api/v3/types/{tipo_bug['id']}"}}}
    if padre:
        cuerpo["_links"]["parent"] = {"href": f"/api/v3/work_packages/{padre['id']}"}
    p = op.por_nombre(op.elementos("/api/v3/priorities"), SEVERIDAD[args.severidad])
    if p:
        cuerpo["_links"]["priority"] = {"href": p["_links"]["self"]["href"]}
    cf = campos_personalizados(op, tipo_bug["id"])
    aplicar_cf(cuerpo, cf, "RF", args.rf)
    wp = op.req("POST", f"/api/v3/projects/{op.pid}/work_packages", cuerpo)
    print(f"Creado OP#{wp['id']}: {wp['subject']}")


def main():
    ap = argparse.ArgumentParser(description="Puente vault ↔ OpenProject del LMS UES")
    sub = ap.add_subparsers(dest="cmd", required=True)
    c = sub.add_parser("cargar"); c.add_argument("--dry-run", action="store_true"); c.set_defaults(fn=cargar)
    e = sub.add_parser("estado"); e.add_argument("op_id"); e.add_argument("estado"); e.set_defaults(fn=estado)
    m = sub.add_parser("comentar"); m.add_argument("op_id"); m.add_argument("texto"); m.set_defaults(fn=comentar)
    b = sub.add_parser("bug"); b.add_argument("--rf", required=True); b.add_argument("--titulo", required=True)
    b.add_argument("--severidad", choices=list(SEVERIDAD), default="Media"); b.add_argument("--descripcion")
    b.set_defaults(fn=bug)
    args = ap.parse_args()
    args.fn(args)


if __name__ == "__main__":
    main()
