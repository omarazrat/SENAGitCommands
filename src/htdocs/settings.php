<?php
/**
 * Configuración de la aplicación: encabezado, pie de página, tamaño de página y tema.
 */
require_once __DIR__ . '/helpers.php';
session_start();

$pageTitle = 'Configuración';
$current   = 'settings';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Sesión inválida, intenta de nuevo.';
    } else {
        $changes = [
            'site_name'    => trim((string)($_POST['site_name'] ?? '')),
            'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
            'header_text'  => trim((string)($_POST['header_text'] ?? '')),
            'footer_text'  => trim((string)($_POST['footer_text'] ?? '')),
            'page_size'    => max(1, (int)($_POST['page_size'] ?? 10)),
            'theme'        => in_array($_POST['theme'] ?? '', ['auto', 'light', 'dark'], true) ? $_POST['theme'] : 'auto',
        ];
        if ($changes['site_name'] === '') {
            $errors[] = 'El nombre del sitio no puede quedar vacío.';
        }
        if (!$errors) {
            if (save_settings($changes)) {
                flash_set('success', 'Configuración guardada correctamente.');
            } else {
                $errors[] = 'No se pudo escribir en settings.json. Verifica los permisos de escritura de la carpeta htdocs.';
            }
            if (!$errors) {
                header('Location: settings.php');
                exit;
            }
        }
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

$s = get_settings();
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-body border-bottom-0 fw-semibold">
                <i class="bi bi-sliders me-1"></i>Textos de la aplicación
            </div>
            <div class="card-body">
                <form method="post" action="settings.php" autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="site_name">Nombre del sitio</label>
                        <input type="text" class="form-control" id="site_name" name="site_name"
                               value="<?php echo e($s['site_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="site_tagline">Lema / subtítulo</label>
                        <input type="text" class="form-control" id="site_tagline" name="site_tagline"
                               value="<?php echo e($s['site_tagline']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="header_text">Encabezado (página principal)</label>
                        <input type="text" class="form-control" id="header_text" name="header_text"
                               value="<?php echo e($s['header_text']); ?>">
                        <div class="form-text">Texto destacado que aparece en la página principal.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="footer_text">Pie de página</label>
                        <input type="text" class="form-control" id="footer_text" name="footer_text"
                               value="<?php echo e($s['footer_text']); ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="page_size">Registros por página</label>
                            <input type="number" min="1" max="100" class="form-control" id="page_size" name="page_size"
                                   value="<?php echo e((int)$s['page_size']); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="theme">Tema por defecto</label>
                            <select class="form-select" id="theme" name="theme">
                                <option value="auto" <?php echo $s['theme'] === 'auto' ? 'selected' : ''; ?>>Automático (sigue al sistema)</option>
                                <option value="light" <?php echo $s['theme'] === 'light' ? 'selected' : ''; ?>>Claro</option>
                                <option value="dark" <?php echo $s['theme'] === 'dark' ? 'selected' : ''; ?>>Oscuro</option>
                            </select>
                            <div class="form-text">Los visitantes pueden cambiar el tema con el botón del menú.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="bi bi-save me-1"></i>Guardar configuración
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-body border-bottom-0 fw-semibold">
                <i class="bi bi-info-circle me-1"></i>Información técnica
            </div>
            <div class="card-body small text-body-secondary">
                <p>
                    <i class="bi bi-database me-1"></i> Base de datos:
                    <code><?php echo e(DB_NAME); ?></code>
                </p>
                <p>
                    <i class="bi bi-hdd-network me-1"></i> Servidor MySQL:
                    <code><?php echo e(DB_HOST); ?></code>
                </p>
                <p>
                    <i class="bi bi-file-earmark-code me-1"></i> Configuración persistida en:
                    <code>htdocs/settings.json</code>
                </p>
                <hr>
                <p class="mb-0">
                    Los editores ignoran las columnas <code>id</code> (no son modificables y tampoco
                    aparecen en los listados). Las columnas que referencian otras tablas (claves foráneas)
                    se muestran como listas desplegables con los datos relacionados.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>