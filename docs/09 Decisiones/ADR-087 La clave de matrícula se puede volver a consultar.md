---
tipo: adr
id: ADR-087
estado: pendiente
fecha: 2026-11-22
afecta: ["[[RF29]]", "[[ADR-050 Inscripción por catálogo y claves compartidas]]"]
fuentes: ["Revisión de Claves por asignatura en producción", "Pedido del usuario del 22/11/2026"]
---

# ADR-087 · La clave de matrícula se puede volver a consultar

> [!info] Pendiente de aceptar por el equipo. **Modifica la regla de RF29 «la clave se guarda con hash» y el texto «se muestra una sola vez».**

## Contexto
- La clave de cada asignatura se guardaba solo con hash (`enrollment_keys.key_hash`): se mostraba una vez al generarla y, si se perdía, había que generar otra. Eso deja sin efecto la clave que los estudiantes ya tenían.
- Quien gestiona las claves (Jefe, Coordinador, Administrador) necesita volver a consultarlas para compartirlas de nuevo.

## Opciones
- **A. Guardarla además cifrada con la APP_KEY** y mostrarla con «Ver clave».
- **B. Guardarla en claro.**
- **C. Mantener solo el hash.**

## Decisión
**A.** *Pedido por el usuario el 22/11/2026.*
- Nueva columna `enrollment_keys.key_encrypted` (`Crypt::encryptString`, AES-256 con la APP_KEY). La verificación al inscribirse sigue usando `key_hash`.
- «Ver clave» la muestra solo a quien tiene `matricula.claves.gestionar` sobre esa asignatura, con el mismo alcance que generarla (`MatriculaService::verClave`).
- Las claves creadas antes de este cambio quedan con `key_encrypted` nulo: se rotulan «no consultable» y se recuperan cambiándolas.

## Consecuencias
- Una copia de la base sin la APP_KEY no revela las claves. Con la APP_KEY sí: quien administra el servidor puede leerlas.
- Si la APP_KEY cambia, las claves cifradas con la anterior dejan de poder consultarse (siguen funcionando para inscribirse); se cambian para volver a verlas.
