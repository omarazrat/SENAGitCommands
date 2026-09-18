<?php
/**
 * Configuración general de la aplicación (InfinityFree).
 * Ajusta estos valores según tu cuenta.
 */

// --- Conexión MySQL ---
define('DB_HOST', 'sql303.infinityfree.com');
define('DB_PORT', 3306);
define('DB_NAME', 'if0_41502436_SENA_Ejercicio_BD1');
define('DB_USER', 'if0_41502436');
define('DB_PASS', 'ddSBX4IEdMDKhL');

// Archivo donde se guardan los ajustes editables (encabezado, pie, etc.)
// Debe quedar dentro de htdocs (el hosting permite escribir aquí).
define('SETTINGS_FILE', __DIR__ . '/settings.json');

/**
 * Valores por defecto de los ajustes configurables.
 * Se sobreescriben con settings.json cuando existe.
 */
$DEFAULT_SETTINGS = [
    'site_name'    => 'Inventario SENA',
    'site_tagline' => 'Gestión de inventario y ventas',
    'header_text'  => 'Sistema de gestión de inventario — SENA',
    'footer_text'  => 'Aplicación de demostración · Base de datos SENA · PHP + Bootstrap',
    'page_size'    => 10,
    'theme'        => 'auto', // auto | light | dark
];

/**
 * Devuelve los ajustes actuales (JSON + valores por defecto).
 */
function get_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        if (is_file(SETTINGS_FILE)) {
            $data = json_decode((string)file_get_contents(SETTINGS_FILE), true);
            if (is_array($data)) {
                $settings = $data;
            }
        }
    }
    return array_merge($GLOBALS['DEFAULT_SETTINGS'], $settings);
}

/**
 * Devuelve un único ajuste.
 */
function setting(string $key)
{
    return get_settings()[$key] ?? null;
}

/**
 * Mezcla los cambios con los ajustes actuales y los persiste en JSON.
 * Devuelve true si se escribió correctamente.
 */
function save_settings(array $changes): bool
{
    $merged = array_merge(get_settings(), $changes);
    $ok = @file_put_contents(SETTINGS_FILE, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $ok !== false;
}