<?php

/**
 * Página "Acerca de" con descripción del proyecto, tecnologías y resumen de la base de datos.
 */
require_once __DIR__ . '/helpers.php';
session_start();

$pageTitle    = 'Acerca de';
$pageSubtitle = 'Información sobre esta aplicación';
$current      = 'acerca';

$tech = [
    ['icon' => 'bi-filetype-php',    'name' => 'PHP',          'desc' => 'Lógica del servidor y operaciones CRUD sobre la base de datos.'],
    ['icon' => 'bi-database',        'name' => 'MySQL',        'desc' => 'Motor de bases de datos que almacena las entidades del sistema.'],
    ['icon' => 'bi-bootstrap',       'name' => 'Bootstrap 5',  'desc' => 'Interfaz responsiva con soporte de tema claro y oscuro.'],
    ['icon' => 'bi-filetype-json',   'name' => 'settings.json', 'desc' => 'Configuración persistente (textos, paginación y tema).'],
    ['icon' => 'bi-moon-stars',      'name' => 'Tema dinámico', 'desc' => 'El usuario puede alternar entre claro y oscuro desde el menú.'],
];

require __DIR__ . '/layout/header.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-body border-bottom-0 fw-semibold">
                <i class="bi bi-box-seam me-1"></i>Sobre esta aplicación
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Aplicación web para gestionar el inventario de una distribuidora de papelería:
                    <strong>bodegas, muebles, paquetes</strong>, <strong>clientes</strong>, <strong>usuarios</strong>
                    y <strong>ventas</strong>.
                </p>
                <p class="mb-2">
                    Los paquetes (resmas, blocks y cajas) se organizan en una jerarquía de contenedores:
                    las cajas agrupan resmas o blocks, y las cajas gaylor agrupan cajas. La aplicación
                    permite listar, crear, editar y eliminar registros de cada entidad.
                </p>
                <p class="mb-0">Proyecto de ejercicio académico del SENA (base de datos relacional).</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-body border-bottom-0 fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-collection me-1"></i>Entidades de la base de datos</span>
                <span class="badge text-bg-primary"><?php echo e(count($GLOBALS['ENTITIES'])); ?> tablas</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Entidad</th>
                            <th scope="col">Tabla</th>
                            <th class="text-end" scope="col">Registros</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($GLOBALS['ENTITIES'] as $key => $label): ?>
                            <?php
                            try {
                                $st = db()->prepare('SELECT COUNT(*) AS n FROM ' . qcol($key));
                                $st->execute();
                                $count = (int)$st->fetch()['n'];
                            } catch (PDOException $e) {
                                $count = null;
                            }
                            $icon = $GLOBALS['ENTITY_ICONS'][$key] ?? 'bi-table';
                            ?>
                            <tr>
                                <td>
                                    <i class="bi <?php echo e($icon); ?> text-primary me-2"></i><?php echo e($label); ?>
                                </td>
                                <td><code><?php echo e($key); ?></code></td>
                                <td class="text-end">
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $current === 'acerca' ? 'active' : ''; ?>" href="acerca.php">
                                            <i class="bi bi-info-circle me-1"></i>Acerca de
                                        </a>
                                    </li> 
                                    <?php echo e($count === null ? '—' : number_format($count)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-body border-bottom-0 fw-semibold">
                <i class="bi bi-hdd-network me-1"></i>Información técnica
            </div>
            <div class="card-body small text-body-secondary">
                <p class="mb-1">
                    <i class="bi bi-database me-1"></i> Base de datos:
                    <code><?php echo e(DB_NAME); ?></code>
                </p>
                <p class="mb-1">
                    <i class="bi bi-hdd-network me-1"></i> Servidor MySQL:
                    <code><?php echo e(DB_HOST); ?></code>
                </p>
                <p class="mb-1">
                    <i class="bi bi-box-seam me-1"></i> Versión del motor:
                    <code><?php echo e(db()->getAttribute(PDO::ATTR_SERVER_VERSION)); ?></code>
                </p>
                <p class="mb-1">
                    <i class="bi bi-filetype-php me-1"></i> PHP:
                    <code><?php echo e(PHP_VERSION); ?></code>
                </p>
                <p class="mb-0">
                    <i class="bi bi-file-earmark-code me-1"></i> Scripts SQL:
                    <code>sql/CreateDB.sql</code>, <code>InsertUsuarios.sql</code>,
                    <code>InsertClientes.sql</code> y <code>InsertPaquetes.sql</code>.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-body border-bottom-0 fw-semibold">
                <i class="bi bi-stack me-1"></i>Tecnologías
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($tech as $t): ?>
                    <li class="list-group-item d-flex gap-3">
                        <i class="bi <?php echo e($t['icon']); ?> fs-5 text-primary"></i>
                        <div>
                            <div class="fw-semibold"><?php echo e($t['name']); ?></div>
                            <div class="small text-body-secondary"><?php echo e($t['desc']); ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-body border-bottom-0 fw-semibold">
                <i class="bi bi-mortarboard me-1"></i>Créditos
            </div>
            <div class="card-body">
                <p class="mb-0 small">
                    Desarrollado como ejercicio del SENA — Análisis y desarrollo de software.
                    <i class="bi bi-github ms-1"></i> Repositorio: <code>SENAGitCommands</code>.
                </p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>