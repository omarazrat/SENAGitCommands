<?php
/**
 * Funciones auxiliares: entidades, metadatos de tablas, formularios, paginación.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Entidades de la base de datos (tablas) → etiquetas de menú.
 */
$ENTITIES = [
    'bodega'      => 'Bodegas',
    'cliente'     => 'Clientes',
    'item_venta'  => 'Ítems de venta',
    'mueble'      => 'Muebles',
    'paquete'     => 'Paquetes',
    'producto'    => 'Productos',
    'rol'         => 'Roles',
    'usuario'     => 'Usuarios',
    'usuario_rol' => 'Usuarios y roles',
    'venta'       => 'Ventas',
];

/**
 * Iconos (Bootstrap Icons) para cada entidad.
 */
$ENTITY_ICONS = [
    'bodega'      => 'bi-buildings',
    'cliente'     => 'bi-person-lines-fill',
    'item_venta'  => 'bi-receipt',
    'mueble'      => 'bi-box-seam',
    'paquete'     => 'bi-box',
    'producto'    => 'bi-boxes',
    'rol'         => 'bi-person-badge',
    'usuario'     => 'bi-people',
    'usuario_rol' => 'bi-person-lock',
    'venta'       => 'bi-cart3',
];

/**
 * Alias: cuando una columna NO se llama igual que la tabla a la que referencia.
 * contenedor -> paquete ; vendedor -> usuario
 */
$FK_ALIAS = [
    'contenedor' => 'paquete',
    'vendedor'   => 'usuario',
];

/**
 * Orden de preferencia para elegir la columna "etiqueta" de una tabla referenciada.
 */
$FK_LABELS = ['nombre', 'descripcion', 'numero', 'login', 'codigo', 'identificacion', 'serial', 'categoria'];

// ------------------------------------------------------------ utilidades básicas

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function qcol(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function col_label(string $name): string
{
    return ucwords(str_replace('_', ' ', $name));
}

// ------------------------------------------------------------ metadatos

function enum_options(string $colType): ?array
{
    if (preg_match("/^enum\((.*)\)$/s", $colType, $m)) {
        preg_match_all("/'([^']*)'/", $m[1], $mm);
        return $mm[1];
    }
    return null;
}

/**
 * Metadatos de la tabla referenciada por una columna FK.
 */
function fk_meta(string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $st = db()->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $st->execute([DB_NAME, $table]);
    $names = array_column($st->fetchAll(), 'COLUMN_NAME');

    $label = 'id';
    foreach ($GLOBALS['FK_LABELS'] as $cand) {
        if (in_array($cand, $names, true)) {
            $label = $cand;
            break;
        }
    }
    $pk = in_array('id', $names, true) ? 'id' : ($names[0] ?? 'id');
    return $cache[$table] = ['table' => $table, 'pk' => $pk, 'label' => $label];
}

/**
 * Detecta la FK de una columna: si la columna se llama igual que otra tabla,
 * o aparece en $FK_ALIAS, se trata de una clave foránea.
 */
function fk_for(string $colName, array $meta)
{
    // sobrescritura explícita
    if (isset($GLOBALS['FK_ALIAS'][$colName])) {
        return fk_meta($GLOBALS['FK_ALIAS'][$colName]);
    }
    // la columna se llama igual que una tabla existente
    if ($colName !== 'id' && array_key_exists($colName, $GLOBALS['ENTITIES']) && $meta['type'] !== 'text') {
        return fk_meta($colName);
    }
    return null;
}

/**
 * Metadatos de todas las columnas de una tabla.
 */
function table_meta(string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $st = db()->prepare(
        'SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION'
    );
    $st->execute([DB_NAME, $table]);

    $cols = [];
    while ($r = $st->fetch()) {
        $name    = $r['COLUMN_NAME'];
        $isAuto  = stripos((string)$r['EXTRA'], 'auto_increment') !== false;
        $meta    = [
            'name'     => $name,
            'type'     => $r['DATA_TYPE'],
            'ctype'    => $r['COLUMN_TYPE'],
            'nullable' => $r['IS_NULLABLE'] === 'YES',
            'pk'       => $r['COLUMN_KEY'] === 'PRI',
            'auto'     => $isAuto,
            'default'  => $r['COLUMN_DEFAULT'],
            'enum'     => enum_options((string)$r['COLUMN_TYPE']),
            'fk'       => null,
        ];
        // required = obligatorio y sin valor por defecto
        $meta['required'] = !$meta['nullable'] && $meta['default'] === null && !$meta['auto'];
        $meta['fk'] = fk_for($name, $meta);
        $cols[] = $meta;
    }
    return $cache[$table] = $cols;
}

// ------------------------------------------------------------ opciones FK

function fk_option_map_all(array $fk): array
{
    $st = db()->query(
        'SELECT ' . qcol($fk['pk']) . ', ' . qcol($fk['label']) . ' FROM ' . qcol($fk['table']) .
        ' ORDER BY ' . qcol($fk['label'])
    );
    return $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

function fk_option_map_for_ids(array $fk, array $ids): array
{
    $ids = array_values(array_unique(array_filter($ids, function ($v) {
        return $v !== null && $v !== '';
    })));
    $ids = array_map('strval', $ids);
    if (!$ids) {
        return [];
    }
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $st   = db()->prepare(
        'SELECT ' . qcol($fk['pk']) . ', ' . qcol($fk['label']) . ' FROM ' . qcol($fk['table']) .
        ' WHERE ' . qcol($fk['pk']) . ' IN (' . $ph . ')'
    );
    $st->execute($ids);
    return $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

// ------------------------------------------------------------ celda de tabla

function is_money_col(array $col): bool
{
    return stripos($col['name'], 'precio') !== false || stripos($col['name'], 'valor') !== false;
}

function cell_html(array $col, $row, array $fkCaches): string
{
    $v = $row[$col['name']] ?? null;
    if ($v === null || $v === '') {
        return '<span class="text-body-secondary">—</span>';
    }
    if ($col['fk']) {
        $map = $fkCaches[$col['name']] ?? [];
        return e($map[$v] ?? ('#' . $v));
    }
    if (in_array($col['type'], ['datetime', 'timestamp'], true)) {
        $ts = strtotime($v);
        return $ts ? e(date('Y-m-d H:i', $ts)) : e($v);
    }
    if ($col['type'] === 'date') {
        return e($v);
    }
    if (is_money_col($col)) {
        return e(number_format((float)$v, 2, ',', '.'));
    }
    if ($col['type'] === 'float') {
        return e(number_format((float)$v, 2, ',', '.'));
    }
    return e($v);
}

// ------------------------------------------------------------ formularios

/**
 * Convierte un valor datetime de MySQL a formato de <input type="datetime-local">.
 */
function to_datetime_local(?string $v): string
{
    if (!$v) {
        return '';
    }
    $ts = strtotime($v);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

/**
 * Convierte el valor de <input type="datetime-local"> a formato MySQL.
 */
function from_datetime_local(string $v): string
{
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) {
        $v .= ':00';
    }
    return $v;
}

function is_required(array $col): bool
{
    return $col['required'];
}

function field_html(array $col, ?string $value = null): string
{
    $name  = $col['name'];
    $label = col_label($name);
    $req   = is_required($col);
    $star  = $req ? ' <span class="text-danger">*</span>' : '';

    $html = '<div class="mb-3">';
    $html .= '<label class="form-label fw-semibold" for="f_' . e($name) . '">' . e($label) . $star . '</label>';

    if ($col['fk']) {
        $html .= '<select class="form-select' . ($req ? ' is-invalid-optional' : '') . '" id="f_' . e($name) . '" name="f_' . e($name) . '"' . ($req ? ' required' : '') . '>';
        if (!$req) {
            $html .= '<option value="">— Sin asignar —</option>';
        }
        foreach (fk_option_map_all($col['fk']) as $id => $lbl) {
            $sel = ($value !== null && (string)$value === (string)$id) ? ' selected' : '';
            $html .= '<option value="' . e($id) . '"' . $sel . '>' . e($lbl) . '</option>';
        }
        $html .= '</select>';
    } elseif ($col['enum']) {
        $html .= '<select class="form-select" id="f_' . e($name) . '" name="f_' . e($name) . '"' . ($req ? ' required' : '') . '>';
        foreach ($col['enum'] as $opt) {
            $vr = $value;
            if ($vr === null) {
                $vr = is_string($col['default']) ? $col['default'] : '';
            }
            $sel = (string)$vr === (string)$opt ? ' selected' : '';
            $html .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
        }
        $html .= '</select>';
    } elseif (in_array($col['type'], ['datetime', 'timestamp'], true)) {
        $v = $value !== null ? to_datetime_local((string)$value) : '';
        $html .= '<input type="datetime-local" class="form-control" id="f_' . e($name) . '" name="f_' . e($name) . '" value="' . e($v) . '"' . ($req ? ' required' : '') . '>';
    } elseif ($col['type'] === 'date') {
        $html .= '<input type="date" class="form-control" id="f_' . e($name) . '" name="f_' . e($name) . '" value="' . e((string)$value) . '"' . ($req ? ' required' : '') . '>';
    } elseif (in_array($col['type'], ['decimal', 'float', 'double', 'int', 'integer'], true)) {
        $step = $col['type'] === 'int' || $col['type'] === 'integer' ? '1' : 'any';
        $html .= '<input type="number" step="' . $step . '" class="form-control" id="f_' . e($name) . '" name="f_' . e($name) . '" value="' . e((string)$value) . '"' . ($req ? ' required' : '') . '>';
    } elseif (in_array($col['type'], ['text', 'mediumtext', 'longtext'], true)) {
        $html .= '<textarea class="form-control" id="f_' . e($name) . '" name="f_' . e($name) . '" rows="3"' . ($req ? ' required' : '') . '>' . e((string)$value) . '</textarea>';
    } else {
        $html .= '<input type="text" class="form-control" id="f_' . e($name) . '" name="f_' . e($name) . '" value="' . e((string)$value) . '"' . ($req ? ' required' : '') . '>';
    }
    $html .= '</div>';
    return $html;
}

// ------------------------------------------------------------ paginación

function pagination_html(int $total, int $page, int $size, array $baseQuery): string
{
    $pages = max(1, (int)ceil($total / max(1, $size)));
    if ($pages <= 1) {
        return '';
    }
    $max  = 9;
    $page = max(1, min($page, $pages));

    $start = max(1, $page - (int)floor($max / 2));
    $end   = min($pages, $start + $max - 1);
    $start = max(1, $end - $max + 1);

    $btn = function (int $p, string $label, bool $active = false, bool $disabled = false) use ($baseQuery) {
        $qs = http_build_query(array_merge($baseQuery, ['p' => $p]));
        $cls = $active ? ' active' : ($disabled ? ' disabled' : '');
        return '<li class="page-item' . $cls . '"><a class="page-link" href="crud.php?' . e($qs) . '">' . $label . '</a></li>';
    };

    $html = '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';
    $html .= $page > 1
        ? $btn($page - 1, '<i class="bi bi-chevron-left"></i>')
        : $btn(1, '<i class="bi bi-chevron-left"></i>', false, true);
    for ($i = $start; $i <= $end; $i++) {
        $html .= $btn($i, (string)$i, $i === $page);
    }
    $html .= $page < $pages
        ? $btn($page + 1, '<i class="bi bi-chevron-right"></i>')
        : $btn($pages, '<i class="bi bi-chevron-right"></i>', false, true);
    $html .= '</ul></nav>';
    return $html;
}

// ------------------------------------------------------------ mensajes flash

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = [$type, $message];
}

function flash_get(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ------------------------------------------------------------ CSRF

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf']) && hash_equals(csrf_token(), (string)$_POST['csrf']);
}