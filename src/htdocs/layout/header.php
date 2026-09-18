<?php
/**
 * Encabezado (configurable desde Configuración).
 * Espera las variables: $pageTitle, $pageSubtitle, $current.
 */
if (!isset($pageSubtitle)) { $pageSubtitle = ''; }
if (!isset($current))      { $current = ''; }

$theme = setting('theme');
$initialTheme = in_array($theme, ['light', 'dark'], true) ? $theme : '';
$nav = $GLOBALS['ENTITIES'] ?? [];
$icons = $GLOBALS['ENTITY_ICONS'] ?? [];
?><!DOCTYPE html>
<html lang="es"<?php echo $initialTheme !== '' ? ' data-bs-theme="' . e($initialTheme) . '"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle ?? setting('site_name')); ?> · <?php echo e(setting('site_name')); ?></title>

    <script>
        // Aplicar tema guardado antes de pintar (evita parpadeo)
        (function () {
            var s = localStorage.getItem('theme');
            if (s) {
                document.documentElement.setAttribute('data-bs-theme', s);
            } else if (!document.documentElement.hasAttribute('data-bs-theme')) {
                document.documentElement.setAttribute('data-bs-theme',
                    window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            }
        })();
    </script>

    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom" data-bs-theme="dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-grid-1x2-fill fs-4 text-primary"></i>
            <span><?php echo e(setting('site_name')); ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current === 'index' ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-house-door me-1"></i>Inicio
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-database me-1"></i>Entidades
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($nav as $key => $label): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 <?php echo ($current === $key) ? 'active' : ''; ?>" href="crud.php?entidad=<?php echo e($key); ?>">
                                    <i class="bi <?php echo e($icons[$key] ?? 'bi-table'); ?>"></i><?php echo e($label); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current === 'settings' ? 'active' : ''; ?>" href="settings.php">
                        <i class="bi bi-gear me-1"></i>Configuración
                    </a>
                </li>
            </ul>
            <li class="nav-item">
                <a class="nav-link <?php echo $current === 'acerca' ? 'active' : '';?>" href="acerca.php">
                    <i class="bi bi-info-circle me-1"></i>Acerca de
                </a>
            </li>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-light btn-sm" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                </button>
            </div>
        </div>
    </div>
</nav>

<header class="bg-body-tertiary border-bottom">
    <div class="container py-3">
        <h1 class="h3 mb-0 d-flex align-items-center gap-2">
            <i class="bi <?php echo e($icons[$current] ?? 'bi-grid'); ?> text-primary"></i>
            <?php echo e($pageTitle); ?>
        </h1>
        <?php if ($pageSubtitle !== ''): ?>
            <p class="text-body-secondary mb-0 mt-1"><?php echo e($pageSubtitle); ?></p>
        <?php endif; ?>
    </div>
</header>

<main class="container py-4 flex-grow-1">
<?php
$flash = flash_get();
if ($flash):
?>
    <div class="alert alert-<?php echo e($flash[0]); ?> alert-dismissible fade show" role="alert">
        <?php echo e($flash[1]); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>