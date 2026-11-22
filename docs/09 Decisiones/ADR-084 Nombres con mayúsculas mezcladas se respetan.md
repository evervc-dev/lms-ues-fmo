---
tipo: adr
id: ADR-084
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF04]]", "[[ADR-060 Formato de los nombres de usuario]]"]
fuentes: ["Revisión UX/UI del rol Soporte Técnico", "Decisión del usuario del 21/11/2026"]
---

# ADR-084 · Los nombres con mayúsculas mezcladas se respetan

> [!info] Pendiente de aceptar por el equipo. **Modifica el punto 3 de [[ADR-060 Formato de los nombres de usuario]].**

## Contexto
- ADR-060 pasaba siempre los nombres y apellidos a nombre propio. En la revisión UX, «Revisión UX» se guardó como «Revisión Ux», y lo mismo pasa con apellidos como «McDonald».

## Opciones
- **A. Normalizar solo si todo viene en mayúsculas o todo en minúsculas.**
- **B. Guardar tal como se escribe.**
- **C. Mantener la normalización de ADR-060.**

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- «ANA RUIZ» y «ana ruiz» pasan a «Ana Ruiz», con las partículas en minúscula como en ADR-060.
- Lo escrito con mayúsculas mezcladas se guarda igual, solo sin espacios de más.
- Vale para el alta, la edición, la carga masiva y el ingreso con Google (`App\Support\Nombre::normalizar`).

## Consecuencias
- Los nombres ya guardados no cambian.
- Un nombre mal escrito con mayúsculas mezcladas («maría DE LOS ángeles») se guarda así; se corrige desde «Editar datos».
