<?php

/*
|--------------------------------------------------------------------------
| Saneado de HTML (RNF01, RF11)
|--------------------------------------------------------------------------
|
| Perfiles de HTMLPurifier. El único que usa la aplicación es `contenidos`:
| el HTML del editor de páginas del docente (Quill), que se sanea en el
| servidor dentro de App\Modules\Contenidos\Services\ContenidoService.
|
| `default` queda como red de seguridad de clean() sin perfil; se eliminaron
| los perfiles de ejemplo del paquete (test, youtube, custom_definition) para
| que ninguna llamada futura habilite iframes o imágenes sin decidirlo.
|
| Documentación: http://htmlpurifier.org/live/configdoc/plain.html
*/

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        // Sin perfil: solo texto con énfasis básico. No se usa en la aplicación.
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,b,em,i,u,s,ul,ol,li,blockquote',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true],
        ],

        /*
         * Editor de páginas de materiales (RF11, CP-RF11-07).
         *
         * Lista blanca: p, br, strong, b, em, i, u, s, h2, h3, ul, ol, li, blockquote y a.
         * - En `a` solo href, rel y target, con esquemas http y https; HTMLPurifier agrega
         *   target="_blank" y rel con noopener y noreferrer a los enlaces externos.
         * - La alineación viaja como clase de Quill (ql-align-center, -right, -justify) y
         *   Attr.AllowedClasses no deja pasar ninguna otra.
         * - Quedan fuera script, style, iframe, img, table, los atributos on* y javascript:.
         */
        'contenidos' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p[class],br,strong,b,em,i,u,s,h2[class],h3[class],ul,ol,li[class],blockquote[class],a[href|rel|target]',
            'HTML.TargetBlank' => true,
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            'Attr.AllowedClasses' => ['ql-align-center', 'ql-align-right', 'ql-align-justify'],
            'Attr.AllowedRel' => ['noopener', 'noreferrer'],
            'Attr.AllowedFrameTargets' => ['_blank'],
            'Attr.EnableID' => false,
            'CSS.AllowedProperties' => [],
            'URI.AllowedSchemes' => ['http' => true, 'https' => true],
            'URI.DisableExternalResources' => true,
            // Quill ya entrega párrafos; reformatear o quitar los vacíos se comería las líneas en blanco.
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],
    ],
];
