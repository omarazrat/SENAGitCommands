<?php
/**
 * CRUD genérico por entidad (tabla).
 *
 * Acciones:
 *   ?entidad=X&accion=listar            → listado paginado (sin columnas id)
 *   ?entidad=X&accion=crear             → formulario de alta (id auto no se muestra)
 *   ?entidad=X&accion=editar&<pk>=N     → formulario de edición (id no modificable)
 *   ?entidad=X&accion=eliminar&<pk>=N   → elimina (vía POST con confirmación)
 */
require_once __DIR__ . '/helpers.php';
session_start();

$entities = $GLOBALS['ENTITIES'];
$entity   = $_GET['entidad'] ?? '';
if (!array_key_exists($entity, $entities)) {
    header('Location: index.php');
    exit;
}
$cols   = table_meta($entity);
$action = $_GET['accion'] ?? 'listar';
if (!in_array($action, ['listar', 'crear', 'editar', 'eliminar'], true)) {
    $action = 'listar';
}

$pageTitle  = $entities[$entity];
$current    = $entity;

// ------------------------------------------------ utilidades locales

function redirect_list(string $entity, ?string $accion = null, array $extra = []): void
{
    $params = ['entidad' => $entity];
    if ($accion) {
        $params['accion'] = $accion;
    }
    $params = array_merge($params, $extra);
    header('Location: crud.php?' . http_build_query($params));
    exit;
}

function pk_from_request(array $cols, array $source): ?array
{
    $pk = [];
    foreach ($cols as $c) {
        if (!$c['pk']) {
            continue;
        }
        if (!array_key_exists($c['name'], $source)) {
            return null;
        }
        $v = $source[$c['name']];
        if ($v === '' || $v === null) {
            return null;
        }
        $pk[$c['name']] = $v;
    }
    return $pk;
}

function pk_where(array $pk): array
{
    $sql = '';
    $params = [];
    foreach ($pk as $col => $val) {
        $sql .= ($sql !== '' ? ' AND ' : '') . qcol($col) . ' = ?';
        $params[] = $val;
    }
    return [$sql, $params];
}

$listCols = array_values(array_filter($cols, fn($c) => !$c['auto']));
$pkCols   = array_values(array_filter($cols, fn($c) => $c['pk']));

// ------------------------------------------------ acciones

if ($action === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash_set('danger', 'Sesión inválida, intenta de nuevo.');
        redirect_list($entity);
    }
    $pk = pk_from_request($cols, $_POST['pk'] ?? []);
    if ($pk === null) {
        flash_set('danger', 'Registro no encontrado.');
        redirect_list($entity);
    }
    [$wsql, $wparams] = pk_where($pk);
    try {
        db()->prepare('DELETE FROM ' . qcol($entity) . ' WHERE ' . $wsql)->execute($wparams);
        flash_set('success', 'Registro eliminado correctamente.');
    } catch (PDOException $ex) {
        flash_set('danger', 'No se pudo eliminar: ' . $ex->getMessage());
    }
    redirect_list($entity);
}

if ($action === 'crear') {
    $errors   = [];
    $formVals = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_ok()) {
            $errors[] = 'Sesión inválida, intenta de nuevo.';
        } else {
            $insert = [];
            $params = [];
            foreach ($cols as $c) {
                if ($c['auto']) {
                    continue;
                }
                $raw = isset($_POST['f_' . $c['name']]) ? trim((string)$_POST['f_' . $c['name']]) : '';
                if (in_array($c['type'], ['datetime', 'timestamp'], true) && $raw !== '') {
                    $raw = from_datetime_local($raw);
                }
                if ($raw === '') {
                    if ($c['default'] !== null) {
                        continue; // deja que la BD aplique el valor por defecto
                    }
                    if ($c['nullable']) {
                        $insert[$c['name']] = null;
                        $params[] = null;
                        continue;
                    }
                    $errors[] = 'El campo "' . col_label($c['name']) . '" es obligatorio.';
                    continue;
                }
                if (in_array($c['type'], ['int', 'integer', 'decimal', 'float', 'double'], true) && !is_numeric($raw)) {
                    $errors[] = 'El campo "' . col_label($c['name']) . '" debe ser numérico.';
                    continue;
                }
                $insert[$c['name']] = $raw;
                $params[] = $raw;
            }

            if (!$errors && $insert) {
                $names = implode(', ', array_map('qcol', array_keys($insert)));
                $ph    = implode(', ', array_fill(0, count($insert), '?'));
                try {
                    db()->prepare('INSERT INTO ' . qcol($entity) . ' (' . $names . ') VALUES (' . $ph . ')')->execute($params);
                    flash_set('success', 'Registro creado correctamente.');
                    redirect_list($entity);
                } catch (PDOException $ex) {
                    $errors[] = 'No se pudo guardar: ' . $ex->getMessage();
                }
            } elseif (!$errors) {
                $errors[] = 'No hay campos válidos para guardar.';
            }
        }
        foreach ($cols as $c) {
            $formVals[$c['name']] = $_POST['f_' . $c['name']] ?? '';
        }
    }

    require __DIR__ . '/layout/header.php';
    if ($errors) {
        echo '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $err) {
            echo '<li>' . e($err) . '</li>';
        }
        echo '</ul></div>';
    }
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="crud.php?<?php echo e(http_build_query(['entidad' => $entity, 'accion' => 'crear'])); ?>" autocomplete="off">
                <?php echo csrf_field(); ?>
                <div class="row">
                    <?php foreach ($cols as $c): if ($c['auto']) continue; ?>
                        <div class="col-md-6">
                            <?php echo field_html($c, $formVals[$c['name']] ?? null); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    <a href="crud.php?entidad=<?php echo e($entity); ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    require __DIR__ . '/layout/footer.php';
    exit;
}

if ($action === 'editar') {
    $pk = pk_from_request($cols, $_GET);
    if ($pk === null) {
        flash_set('danger', 'Registro no encontrado.');
        redirect_list($entity);
    }
    [$wsql, $wparams] = pk_where($pk);

    $sel   = implode(', ', array_map(fn($c) => qcol($c['name']), $cols));
    $st    = db()->prepare('SELECT ' . $sel . ' FROM ' . qcol($entity) . ' WHERE ' . $wsql . ' LIMIT 1');
    $st->execute($wparams);
    $record = $st->fetch();
    if (!$record) {
        flash_set('danger', 'Registro no encontrado.');
        redirect_list($entity);
    }

    $errors   = [];
    $formVals = $record;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_ok()) {
            $errors[] = 'Sesión inválida, intenta de nuevo.';
        } else {
            $set    = [];
            $params = [];
            foreach ($cols as $c) {
                if ($c['auto']) {
                    continue;
                }
                $raw = isset($_POST['f_' . $c['name']]) ? trim((string)$_POST['f_' . $c['name']]) : '';
                if (in_array($c['type'], ['datetime', 'timestamp'], true) && $raw !== '') {
                    $raw = from_datetime_local($raw);
                }
                if ($raw === '') {
                    // si el campo tiene default o es nullable, no lo pisamos con vacío
                    if ($c['default'] !== null || $c['nullable']) {
                        continue;
                    }
                    $errors[] = 'El campo "' . col_label($c['name']) . '" es obligatorio.';
                    continue;
                }
                if (in_array($c['type'], ['int', 'integer', 'decimal', 'float', 'double'], true) && !is_numeric($raw)) {
                    $errors[] = 'El campo "' . col_label($c['name']) . '" debe ser numérico.';
                    continue;
                }
                $set[] = qcol($c['name']) . ' = ?';
                $params[] = $raw;
            }

            if (!$errors && $set) {
                $sql    = 'UPDATE ' . qcol($entity) . ' SET ' . implode(', ', $set) . ' WHERE ' . $wsql;
                $params = array_merge($params, $wparams);
                try {
                    db()->prepare($sql)->execute($params);
                    flash_set('success', 'Registro actualizado correctamente.');
                    redirect_list($entity);
                } catch (PDOException $ex) {
                    $errors[] = 'No se pudo actualizar: ' . $ex->getMessage();
                }
            } elseif (!$errors) {
                $errors[] = 'No hay campos para actualizar.';
            }
        }
        foreach ($cols as $c) {
            $formVals[$c['name']] = $_POST['f_' . $c['name']] ?? '';
        }
    }

    require __DIR__ . '/layout/header.php';

    $autoCol = null;
    foreach ($cols as $c) {
        if ($c['auto']) { $autoCol = $c; break; }
    }
    if ($autoCol !== null && isset($record[$autoCol['name']])) {
        echo '<div class="alert alert-light border small d-inline-block mb-3">
                  <i class="bi bi-hash text-body-secondary"></i> Corrigiendo el registro #
                  <strong>' . e($record[$autoCol['name']]) . '</strong> (el id no se modifica)
              </div>';
    }
    if ($errors) {
        echo '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $err) {
            echo '<li>' . e($err) . '</li>';
        }
        echo '</ul></div>';
    }
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="crud.php?<?php echo e(http_build_query(array_merge(['entidad' => $entity, 'accion' => 'editar'], $pk))); ?>" autocomplete="off">
                <?php echo csrf_field(); ?>
                <?php foreach ($pk as $pkCol => $pkVal): ?>
                    <input type="hidden" name="pk[<?php echo e($pkCol); ?>]" value="<?php echo e($pkVal); ?>">
                <?php endforeach; ?>
                <div class="row">
                    <?php foreach ($cols as $c): if ($c['auto']) continue; ?>
                        <div class="col-md-6">
                            <?php echo field_html($c, $formVals[$c['name']] ?? null); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
                    <a href="crud.php?entidad=<?php echo e($entity); ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    require __DIR__ . '/layout/footer.php';
    exit;
}

// ------------------------------------------------ listar (por defecto)

$page = max(1, (int)($_GET['p'] ?? 1));
$size = (int)setting('page_size');
if ($size < 1) { $size = 10; }
$q = trim((string)($_GET['q'] ?? ''));

$textCols = array_values(array_filter($cols, fn($c) => in_array($c['type'], ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum'], true)));

$where = [];
$bind  = [];
if ($q !== '' && $textCols) {
    $parts = [];
    foreach ($textCols as $c) {
        $parts[] = qcol($c['name']) . ' LIKE ?';
        $bind[]  = '%' . $q . '%';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$st = db()->prepare('SELECT COUNT(*) AS n FROM ' . qcol($entity) . $whereSql);
$st->execute($bind);
$total = (int)$st->fetch()['n'];

$selCols   = $listCols;
$selNames  = array_map(fn($c) => $c['name'], $selCols);
foreach ($pkCols as $pc) {
    if (!in_array($pc['name'], $selNames, true)) {
        $selCols[] = $pc; // la PK viaja en el SELECT para los enlaces, sin mostrarse en el listado
    }
}
$sel = implode(', ', array_map(fn($c) => qcol($c['name']), $selCols));
$ord = implode(', ', array_map(fn($c) => qcol($c['name']), $pkCols ?: [$listCols[0]]));

$st = db()->prepare('SELECT ' . $sel . ' FROM ' . qcol($entity) . $whereSql . ' ORDER BY ' . $ord . ' LIMIT ? OFFSET ?');
$i = 1;
foreach ($bind as $b) {
    $st->bindValue($i++, $b);
}
$st->bindValue($i++, $size, PDO::PARAM_INT);
$st->bindValue($i++, ($page - 1) * $size, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

$fkCaches = [];
foreach ($listCols as $c) {
    if (!$c['fk']) {
        continue;
    }
    $ids = array_column($rows, $c['name']);
    $fkCaches[$c['name']] = fk_option_map_for_ids($c['fk'], array_map('strval', $ids));
}

require __DIR__ . '/layout/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="text-body-secondary">
        <i class="bi bi-funnel me-1"></i><?php echo e($total); ?> registro<?php echo $total === 1 ? '' : 's'; ?>
        <?php if ($q !== ''): ?> (filtrados por "<?php echo e($q); ?>")<?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($textCols): ?>
        <form method="get" action="crud.php" class="d-flex gap-2" role="search">
            <input type="hidden" name="entidad" value="<?php echo e($entity); ?>">
            <input type="search" class="form-control form-control-sm" name="q" value="<?php echo e($q); ?>" placeholder="Buscar…">
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php endif; ?>
        <a href="crud.php?<?php echo e(http_build_query(['entidad' => $entity, 'accion' => 'crear'])); ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Nuevo
        </a>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="text-center text-body-secondary py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No hay registros<?php echo $q !== '' ? ' que coincidan con la búsqueda' : ' todavía'; ?>.
    </div>
<?php else: ?>
<div class="table-responsive card border-0 shadow-sm">
    <table class="table table-hover align-middle mb-0">
        <thead>
        <tr>
            <?php foreach ($listCols as $c): ?>
                <th scope="col"><?php echo e(col_label($c['name'])); ?></th>
            <?php endforeach; ?>
            <th scope="col" class="text-end">Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $pk = [];
            foreach ($pkCols as $c) {
                $pk[$c['name']] = $row[$c['name']] ?? '';
            }
            $editUrl  = 'crud.php?' . e(http_build_query(array_merge(['entidad' => $entity, 'accion' => 'editar'], $pk)));
            $delUrl   = 'crud.php?' . e(http_build_query(['entidad' => $entity, 'accion' => 'eliminar']));
            ?>
            <tr>
                <?php foreach ($listCols as $c): ?>
                    <td><?php echo cell_html($c, $row, $fkCaches); ?></td>
                <?php endforeach; ?>
                <td class="text-end text-nowrap">
                    <a href="<?php echo $editUrl; ?>" class="btn btn-outline-primary btn-sm" title="Editar"><i class="bi bi-pencil-square"></i></a>
                    <form method="post" action="<?php echo $delUrl; ?>" class="d-inline"
                          onsubmit="return confirm('¿Eliminar este registro? Esta acción no se puede deshacer.');">
                        <?php echo csrf_field(); ?>
                        <?php foreach ($pk as $k => $v): ?>
                            <input type="hidden" name="pk[<?php echo e($k); ?>]" value="<?php echo e($v); ?>">
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <span class="text-body-secondary small">
        Mostrando <?php echo e($total === 0 ? 0 : (($page - 1) * $size) + 1); ?>–<?php echo e(min($page * $size, $total)); ?> de <?php echo e($total); ?>
    </span>
    <?php echo pagination_html($total, $page, $size, array_merge(['entidad' => $entity], $q !== '' ? ['q' => $q] : [])); ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>