/**
 * Normaliza una respuesta eliminando acentos, mayúsculas y caracteres especiales
 */
function normalizar_respuesta($txt) {
    $txt = mb_strtolower($txt, 'UTF-8');
    $txt = strtr($txt, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
        'Ä'=>'a','Ë'=>'e','Ï'=>'i','Ö'=>'o','Ü'=>'u',
    ]);
    return preg_replace('/[^a-z0-9 ]/u','',$txt);
}
