<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2-4">
        <div class="card stat-card blue p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Productos</div>
                    <div class="fs-3 fw-bold"><?= $total_productos ?></div>
                </div>
                <i class="bi bi-box fs-2 text-primary opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2-4">
        <div class="card stat-card green p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Tiendas</div>
                    <div class="fs-3 fw-bold"><?= $total_tiendas ?></div>
                </div>
                <i class="bi bi-shop fs-2 text-success opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2-4">
        <div class="card stat-card orange p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Proveedores</div>
                    <div class="fs-3 fw-bold"><?= $total_proveedores ?></div>
                </div>
                <i class="bi bi-truck fs-2 text-warning opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2-4">
        <div class="card stat-card purple p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Marcas</div>
                    <div class="fs-3 fw-bold"><?= $total_marcas ?></div>
                </div>
                <i class="bi bi-tags fs-2 text-purple opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2-4">
        <div class="card stat-card teal p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Categorías</div>
                    <div class="fs-3 fw-bold"><?= $total_categorias ?></div>
                </div>
                <i class="bi bi-diagram-3 fs-2 text-info opacity-50"></i>
            </div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card p-3">
            <h6 class="fw-semibold mb-3"><i class="bi bi-lightning me-2 text-warning"></i>Accesos Rápidos</h6>
            <div class="d-grid gap-2">
                <a href="/productos/create" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Producto
                </a>
                <a href="/marcas/create" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-plus-circle me-2"></i>Nueva Marca
                </a>
                <a href="/proveedores/create" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Proveedor
                </a>
                <a href="/tiendas/create" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-plus-circle me-2"></i>Nueva Tienda
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3">
            <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle me-2 text-info"></i>Acerca de CentralPIM</h6>
            <p class="text-muted small mb-2">Sistema centralizado de gestión de catálogo multi-tienda. Administra productos, precios y stock en un solo lugar.</p>
            <ul class="list-unstyled small text-muted">
                <li><i class="bi bi-check2 text-success me-2"></i>Catálogo centralizado</li>
                <li><i class="bi bi-check2 text-success me-2"></i>Precios por tienda con fallback</li>
                <li><i class="bi bi-check2 text-success me-2"></i>Stock por canal de venta</li>
                <li><i class="bi bi-check2 text-success me-2"></i>Jerarquía de categorías</li>
            </ul>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
