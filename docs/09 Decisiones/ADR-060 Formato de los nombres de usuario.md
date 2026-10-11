---
tipo: adr
id: ADR-060
estado: pendiente
fecha: 2026-10-30
afecta: ["[[RF04]]", "[[RF01]]"]
fuentes: ["Informe de QA del rol Administrador, versión del 30/10/2026 (pruebas 1 y 3 de Usuarios)", "Decisión del usuario (30/10/2026)"]
---

# ADR-060 · Formato de los nombres de usuario

> [!warning] Pendiente de aceptar

## Contexto
La QA propuso separar el nombre en cuatro campos (primer y segundo nombre, primer y segundo apellido), con un tope de unos 25 caracteres por campo y un único formato de mayúsculas. El motivo: había nombres pegados o mal escritos, y una cuenta con un apellido de unas 80 «m» seguidas estiraba la tabla de Usuarios hasta ocultar sus acciones.

El modelo tiene `first_name` y `last_name` ([[Modelo entidad-relación]]). Las cuentas nacen de tres fuentes: el alta manual, la carga masiva (columnas `nombres` y `apellidos`) y el perfil de Google (`given_name` y `family_name`, [[ADR-038 Autorregistro sin rol]]). **Ninguna fuente trae cuatro campos.**

## Decisión
*Decidida por el usuario el 30/10/2026.*

1. **Se mantienen dos campos**, nombres y apellidos. No hay migración.
2. **Formato** (`App\Support\Nombre`): como mucho **60 caracteres** por campo y **ninguna palabra de más de 25 letras**. El alta, la edición y la carga masiva rechazan lo que no cumple, con un mensaje que dice qué corregir.
3. **Se guardan como nombre propio:** «maría DE LOS ángeles» pasa a «María de los Ángeles». Las partículas (de, del, la, las, los, y, e, da, van, von) van en minúscula salvo al inicio.
4. **El ingreso con Google solo normaliza:** a nadie se le niega la entrada por cómo escribió su nombre en Google.
5. **No es retroactivo:** las cuentas existentes conservan su nombre hasta que alguien lo edite.

## Consecuencias
- Un nombre pegado pero corto («JoseAntonio», 11 letras) sigue pasando: no hay forma fiable de detectarlo. Lo evita la revisión de quien crea la cuenta.
- Nombres con mayúsculas internas («McDonald», «DeLeón») quedan como «Mcdonald» y «Deleón». Es un costo aceptable frente a la mezcla de mayúsculas que reportó la QA.
- Si más adelante se necesitan cuatro campos (por ejemplo, para un sistema externo), hará falta otra ADR con migración y un criterio para partir los nombres que traen Google y la carga masiva.
