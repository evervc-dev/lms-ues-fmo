---
tipo: adr
id: ADR-039
estado: pendiente
fecha: 2026-10-17
afecta: ["[[RF01]]", "[[RF04]]", "[[Modelo entidad-relación]]", "[[ADR-007 Inicio de sesión con Google]]", "[[ADR-020 Hosting y almacenamiento]]"]
fuentes: ["Decisión del equipo (17/10/2026)"]
---

# ADR-039 · Foto de perfil enlazada desde Google, sin descarga

## Contexto
El sistema mostraba solo las iniciales del usuario. Google entrega en el inicio de sesión el claim `picture` con la foto de la cuenta. El equipo quiere usarla, pero sin descargar ni alojar imágenes: si el sistema guardara archivos, podría terminar sirviendo contenido inapropiado desde su propio dominio.

## Decisión
*Definida por el equipo el 17/10/2026; pendiente de aceptación.*

1. **Solo se guarda el enlace** en `users.avatar_url` (máx. 500). La imagen nunca se descarga ni pasa por el servidor.
2. **Solo se aceptan enlaces `https` de `googleusercontent.com`** o de sus subdominios. Cualquier otro valor se guarda como nulo.
3. **Se actualiza en cada ingreso**, en la misma escritura del login (`DbContext::run`, auditada). Si la persona cambia o quita su foto en Google, el sistema lo refleja al siguiente ingreso.
4. **Se muestra** con `referrerpolicy="no-referrer"` en el menú, Mi perfil, la lista de Usuarios y la ficha. No se muestra en las listas académicas. Si la imagen no carga, se ven las iniciales.
5. **El contenido de la foto** lo gobiernan la cuenta de Google y las políticas del Workspace de la UES, no este sistema.

## Consecuencias
- Columna nueva `users.avatar_url` (migración `2026_10_17_234710_add_avatar_url_to_users_table`).
- Cada vista con foto hace una petición del navegador a `googleusercontent.com`. Si se agrega una Content-Security-Policy con `img-src`, hay que permitir ese dominio.
- Si un enlace caduca, se muestran las iniciales hasta el siguiente ingreso.
