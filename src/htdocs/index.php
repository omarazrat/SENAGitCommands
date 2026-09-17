<?php
/**
 * Página principal (dashboard) con accesos a todas las entidades.
 */
require_once __DIR__ . '/helpers.php';
session_start();

$pageTitle    = setting('site_name');
$pageSubtitle = setting('site_tagline');
$current      = 'index';

require __DIR__ . '/layout/header.php';
?>

<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
        <div class="display-6 text-primary"><i class="bi bi-box-seam-fill"></i></div>
        <div>
            <h2 class="h4 mb-1"><?php echo e(setting('header_text')); ?></h2>
            <p class="text-body-secondary mb-0">
                Selecciona una entidad para gestionar sus registros (listar, crear, editar y eliminar).
                El modo oscuro se alterna desde el botón del menú superior.
            </p>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($GLOBALS['ENTITIES'] as $key => $label): ?>
        <?php
        try {
            $st   = db()->prepare('SELECT COUNT(*) AS n FROM ' . qcol($key));
            $st->execute();
            $count = (int)$st->fetch()['n'];
        } catch (PDOException $e) {
            $count = '—';
        }
        $icon = $GLOBALS['ENTITY_ICONS'][$key] ?? 'bi-table';
        ?>
        <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
            <div class="card entity-card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-3">
                        <div class="entity-icon">
                            <i class="bi <?php echo e($icon); ?>"></i>
                        </div>
                        <div>
                            <div class="text-body-secondary small text-uppercase fw-semibold"><?php echo e($label); ?></div>
                            <div class="fs-4 fw-bold"><?php echo e(is_int($count) ? number_format($count) : $count); ?>
                                <span class="text-body-secondary fs-6 fw-normal">registro<?php echo $count === 1 ? '' : 's'; ?></span>
                            </div>
                        </div>
                    </div>
                    <a href="crud.php?entidad=<?php echo e($key); ?>" class="btn btn-outline-primary btn-sm w-100 mt-3">
                        <i class="bi bi-arrow-right-circle me-1"></i>Gestionar
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>