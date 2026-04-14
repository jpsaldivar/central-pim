<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Formulario oculto para acciones masivas -->
<form id="bulk-form" action="<?= site_url('productos/bulk') ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="accion"       id="bulk-accion"       value="">
    <input type="hidden" name="marca_id"     id="bulk-marca-id"     value="">
    <input type="hidden" name="categoria_id" id="bulk-categoria-id" value="">
    <input type="hidden" name="tienda_id"    id="bulk-tienda-id"    value="">
</form>

<div class="card">
    <div class="card-body">

        <!-- Cabecera -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">Catálogo de Productos</h6>
            <a href="/productos/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nuevo Producto
            </a>
        </div>

        <!-- Barra de acciones masivas (oculta hasta seleccionar algo) -->
        <div id="bulk-bar" class="d-none alert alert-secondary py-2 px-3 mb-3 d-flex align-items-center gap-3">
            <span class="text-muted small">
                <strong><span id="bulk-count">0</span></strong> producto(s) seleccionado(s)
            </span>
            <div class="d-flex gap-2 ms-auto">
                <button type="button" class="btn btn-sm btn-outline-primary"
                        onclick="abrirModalMarca()">
                    <i class="bi bi-tags me-1"></i>Asignar marca
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="abrirModalCategoria()">
                    <i class="bi bi-folder me-1"></i>Asignar categoría
                </button>
                <button type="button" class="btn btn-sm btn-outline-success"
                        onclick="abrirModalTienda()">
                    <i class="bi bi-shop me-1"></i>Activar en tienda
                </button>
            </div>
        </div>

        <table class="table table-hover align-middle table-sm">
            <thead>
                <tr>
                    <th style="width:36px;">
                        <input type="checkbox" id="check-all" class="form-check-input">
                    </th>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Marca</th>
                    <th>Proveedor</th>
                    <th>Precio</th>
                    <th>Costo</th>
                    <th>Stock</th>
                    <?php foreach ($tiendas as $t): ?>
                    <th class="text-center"><?= esc($t['nombre']) ?></th>
                    <?php endforeach; ?>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($productos)): ?>
                <tr>
                    <td colspan="<?= 9 + count($tiendas) ?>" class="text-center text-muted py-4">
                        No hay productos registrados.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($productos as $p): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input row-check"
                               value="<?= $p['id'] ?>">
                    </td>
                    <td class="text-muted"><?= $p['id'] ?></td>
                    <td><?= esc($p['nombre']) ?></td>
                    <td><?= esc($p['marca_nombre'] ?? '—') ?></td>
                    <td><?= esc($p['proveedor_nombre'] ?? '—') ?></td>
                    <td>$<?= number_format($p['precio'], 2) ?>
                        <?php if ($p['precio_oferta']): ?>
                            <br><small class="text-danger">Oferta: $<?= number_format($p['precio_oferta'], 2) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>$<?= number_format($p['costo'], 2) ?></td>
                    <td>
                        <span class="badge <?= $p['stock_general'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                            <?= $p['stock_general'] ?>
                        </span>
                    </td>
                    <?php foreach ($tiendas as $t): ?>
                    <td class="text-center">
                        <?php
                        if (!isset($producto_tiendas[$p['id']][$t['id']])) {
                            echo '<i class="bi bi-x-circle-fill text-danger" title="No disponible en ' . esc($t['nombre']) . '"></i>';
                        } else {
                            $pt    = $producto_tiendas[$p['id']][$t['id']];
                            $stock = $pt['stock_especifico'] !== null ? (int)$pt['stock_especifico'] : (int)$p['stock_general'];
                            $color = $stock > 0 ? 'text-success' : 'text-warning';
                            echo "<i class=\"bi bi-check-circle-fill {$color}\" title=\"Disponible en " . esc($t['nombre']) . "\"></i>";
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="text-end">
                        <a href="/productos/edit/<?= $p['id'] ?>" class="btn btn-outline-secondary btn-action me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/productos/delete/<?= $p['id'] ?>" class="btn btn-outline-danger btn-action"
                           onclick="return confirm('¿Eliminar este producto?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="d-flex align-items-center gap-2">
                <?php if ($pager): ?>
                <small class="text-muted">
                    Página <?= $pager->getCurrentPage() ?> de <?= $pager->getPageCount() ?>
                </small>
                <?php endif ?>
                <select id="per-page-select" class="form-select form-select-sm" style="width:auto;"
                        onchange="cambiarPorPagina(this.value)">
                    <?php foreach ([25, 50, 100, 500, 1000] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>>
                        <?= $opt ?> por página
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <?php if ($pager && $pager->getPageCount() > 1): ?>
            <?= $pager->links('default', 'bootstrap5') ?>
            <?php endif ?>
        </div>

    </div>
</div>

<!-- Modal: Asignar marca -->
<div class="modal fade" id="modalMarca" tabindex="-1" aria-labelledby="modalMarcaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="modalMarcaLabel">
                    <i class="bi bi-tags me-2 text-primary"></i>Asignar Marca
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Selecciona la marca que se asignará a los
                    <strong><span class="modal-count">0</span></strong> producto(s) seleccionado(s).
                </p>
                <select id="modal-marca-select" class="form-select">
                    <option value="">— Sin marca —</option>
                    <?php foreach ($marcas as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= esc($m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarAsignarMarca()">
                    <i class="bi bi-check-lg me-1"></i>Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Asignar categoría -->
<div class="modal fade" id="modalCategoria" tabindex="-1" aria-labelledby="modalCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="modalCategoriaLabel">
                    <i class="bi bi-folder me-2 text-secondary"></i>Asignar Categoría
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    La categoría seleccionada se agregará a los
                    <strong><span class="modal-count">0</span></strong> producto(s) seleccionado(s)
                    sin eliminar las que ya tienen asignadas.
                </p>
                <select id="modal-categoria-select" class="form-select">
                    <option value="">— Selecciona una categoría —</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= esc($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarAsignarCategoria()">
                    <i class="bi bi-check-lg me-1"></i>Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Activar en tienda -->
<div class="modal fade" id="modalTienda" tabindex="-1" aria-labelledby="modalTiendaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="modalTiendaLabel">
                    <i class="bi bi-shop me-2 text-success"></i>Activar en Tienda
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Los <strong><span class="modal-count">0</span></strong> producto(s) seleccionado(s)
                    serán habilitados en la tienda elegida. Los que ya estén activos no serán modificados.
                </p>
                <select id="modal-tienda-select" class="form-select">
                    <option value="">— Selecciona una tienda —</option>
                    <?php foreach ($tiendas as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= esc($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="confirmarActivarTienda()">
                    <i class="bi bi-check-lg me-1"></i>Activar
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const checkAll  = document.getElementById('check-all');
const bulkBar   = document.getElementById('bulk-bar');
const bulkCount = document.getElementById('bulk-count');

function getCheckedIds() {
    return [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
}

function actualizarBarra() {
    const ids = getCheckedIds();
    const n   = ids.length;

    bulkBar.classList.toggle('d-none', n === 0);
    bulkBar.classList.toggle('d-flex', n > 0);
    bulkCount.textContent = n;

    document.querySelectorAll('.modal-count').forEach(el => el.textContent = n);

    // Sincronizar estado del check-all
    const total = document.querySelectorAll('.row-check').length;
    checkAll.indeterminate = n > 0 && n < total;
    checkAll.checked       = n > 0 && n === total;
}

// Seleccionar / deseleccionar todos
checkAll.addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
    actualizarBarra();
});

// Cambio en checkbox individual
document.querySelectorAll('.row-check').forEach(c => {
    c.addEventListener('change', actualizarBarra);
});

// Inyectar los IDs seleccionados en el formulario antes de enviarlo
function prepararFormulario(accion) {
    const form = document.getElementById('bulk-form');

    // Limpiar inputs anteriores
    form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());

    // Agregar los seleccionados
    getCheckedIds().forEach(id => {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });

    document.getElementById('bulk-accion').value = accion;
}

// Cambiar cantidad por página (resetea a página 1)
function cambiarPorPagina(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Abrir modal de marca
function abrirModalMarca() {
    if (getCheckedIds().length === 0) return;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMarca')).show();
}

// Confirmar asignación de marca
function confirmarAsignarMarca() {
    const marcaId = document.getElementById('modal-marca-select').value;
    document.getElementById('bulk-marca-id').value = marcaId;
    prepararFormulario('asignar_marca');
    bootstrap.Modal.getInstance(document.getElementById('modalMarca')).hide();
    document.getElementById('bulk-form').submit();
}

// Abrir modal de categoría
function abrirModalCategoria() {
    if (getCheckedIds().length === 0) return;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCategoria')).show();
}

// Confirmar asignación de categoría
function confirmarAsignarCategoria() {
    const categoriaId = document.getElementById('modal-categoria-select').value;
    if (!categoriaId) return;
    document.getElementById('bulk-categoria-id').value = categoriaId;
    prepararFormulario('asignar_categoria');
    bootstrap.Modal.getInstance(document.getElementById('modalCategoria')).hide();
    document.getElementById('bulk-form').submit();
}

// Abrir modal de tienda
function abrirModalTienda() {
    if (getCheckedIds().length === 0) return;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTienda')).show();
}

// Confirmar activar en tienda
function confirmarActivarTienda() {
    const tiendaId = document.getElementById('modal-tienda-select').value;
    if (!tiendaId) return;
    document.getElementById('bulk-tienda-id').value = tiendaId;
    prepararFormulario('activar_tienda');
    bootstrap.Modal.getInstance(document.getElementById('modalTienda')).hide();
    document.getElementById('bulk-form').submit();
}
</script>
<?= $this->endSection() ?>
