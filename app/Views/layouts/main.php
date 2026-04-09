<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'CentralPIM') ?> - CentralPIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar {
            min-height: 100vh;
            background: #1e2a3a;
            width: 250px;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
            padding-top: 0;
            transition: all 0.3s;
        }
        .sidebar-brand {
            padding: 1.2rem 1.5rem;
            background: #141e2d;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 1px;
            border-bottom: 1px solid #2d3f56;
        }
        .sidebar .nav-link {
            color: #a0aec0;
            padding: .65rem 1.5rem;
            font-size: .9rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background: #2d3f56;
        }
        .sidebar .nav-section {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #4a6080;
            padding: 1rem 1.5rem .3rem;
        }
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: .8rem 1.5rem;
        }
        .content-area { padding: 1.5rem; }
        .card { border: none; box-shadow: 0 1px 3px rgba(0,0,0,.08); border-radius: .5rem; }
        .stat-card { border-left: 4px solid; }
        .stat-card.blue { border-color: #4299e1; }
        .stat-card.green { border-color: #48bb78; }
        .stat-card.orange { border-color: #ed8936; }
        .stat-card.purple { border-color: #9f7aea; }
        .stat-card.teal { border-color: #38b2ac; }
        .table th { font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; color: #718096; border-top: none; }
        .btn-action { padding: .2rem .5rem; font-size: .8rem; }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-box-seam me-2"></i>CentralPIM
    </div>
    <nav class="mt-2">
        <div class="nav-section">Principal</div>
        <a href="/dashboard" class="nav-link <?= (uri_string() === 'dashboard' || uri_string() === '') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <div class="nav-section">Catálogo</div>
        <a href="/productos" class="nav-link <?= str_starts_with(uri_string(), 'productos') ? 'active' : '' ?>">
            <i class="bi bi-box"></i> Productos
        </a>
        <a href="/marcas" class="nav-link <?= str_starts_with(uri_string(), 'marcas') ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Marcas
        </a>
        <a href="/categorias" class="nav-link <?= str_starts_with(uri_string(), 'categorias') ? 'active' : '' ?>">
            <i class="bi bi-diagram-3"></i> Categorías
        </a>
        <div class="nav-section">Operaciones</div>
        <a href="/proveedores" class="nav-link <?= str_starts_with(uri_string(), 'proveedores') ? 'active' : '' ?>">
            <i class="bi bi-truck"></i> Proveedores
        </a>
        <a href="/tiendas" class="nav-link <?= str_starts_with(uri_string(), 'tiendas') ? 'active' : '' ?>">
            <i class="bi bi-shop"></i> Tiendas
        </a>
        <div class="nav-section">Sistema</div>
        <a href="/logout" class="nav-link text-danger">
            <i class="bi bi-box-arrow-left"></i> Cerrar Sesión
        </a>
    </nav>
</div>
<div class="main-content">
    <div class="topbar d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold"><?= esc($title ?? '') ?></h5>
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-circle fs-5 text-muted"></i>
            <small class="text-muted"><?= esc(session()->get('usuario_nombre') ?? '') ?></small>
        </div>
    </div>
    <div class="content-area">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i><?= esc(session()->getFlashdata('error')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <ul class="mb-0 mt-1">
                <?php foreach ((array)session()->getFlashdata('errors') as $e): ?>
                    <li><?= esc($e) ?></li>
                <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?= $this->renderSection('content') ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
