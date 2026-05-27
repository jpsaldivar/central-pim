<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/**
 * Formatea un precio CLP entero como string legible.
 * Ej: 1339990 → "$ 1.339.990"
 */
function fmtClp(?int $precio): string {
    if ($precio === null) return '—';
    return '$ ' . number_format($precio, 0, ',', '.');
}

/**
 * Calcula y formatea la variación porcentual entre dos precios.
 * Devuelve ['pct' => string, 'class' => string] o null si no aplica.
 */
function calcDiff(?int $actual, ?int $anterior): ?array {
    if ($actual === null || $anterior === null || $anterior === 0) return null;
    if ($actual === $anterior) return null;
    $pct = (($actual - $anterior) / $anterior) * 100;
    return [
        'pct'   => ($pct > 0 ? '+' : '') . number_format($pct, 1) . '%',
        'class' => $pct > 0 ? 'text-danger' : 'text-success',
    ];
}
?>

<!-- Encabezado del run -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= site_url('scrapers') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h6 class="fw-semibold mb-0">
            <?= esc($tienda_nombre) ?>
            <span class="text-muted fw-normal mx-1">·</span>
            Run #<?= $run['id'] ?>
            <span class="text-muted fw-normal ms-1"><?= esc(date('d/m/Y H:i', strtotime($run['iniciado_en']))) ?></span>
        </h6>
    </div>
    <?php
        $badgeClass = match($run['estado']) {
            'completado'  => 'bg-success',
            'en_progreso' => 'bg-warning text-dark',
            'error'       => 'bg-danger',
            default       => 'bg-secondary',
        };
    ?>
    <span class="badge <?= $badgeClass ?>"><?= esc($run['estado']) ?></span>
    <?php if (!empty($categorias)): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm ms-auto"
                data-bs-toggle="modal" data-bs-target="#modalCategorias">
            <i class="bi bi-list-ul me-1"></i><?= count($categorias) ?> <?= count($categorias) === 1 ? 'categoría' : 'categorías' ?>
        </button>
    <?php endif; ?>
</div>

<?php if ($run['estado'] === 'error'): ?>
    <div class="alert alert-danger py-2">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Error:</strong> <?= esc($run['mensaje_error']) ?>
    </div>
<?php endif; ?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card blue p-3">
            <div class="text-muted small">Encontrados</div>
            <div class="fw-bold fs-4"><?= $run['total_encontrados'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card green p-3">
            <div class="text-muted small">Nuevos</div>
            <div class="fw-bold fs-4"><?= $run['total_nuevos'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card orange p-3">
            <div class="text-muted small">Cambios de precio</div>
            <div class="fw-bold fs-4"><?= $run['total_cambios'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card purple p-3">
            <div class="text-muted small">Sin vincular</div>
            <div class="fw-bold fs-4"><?= $sin_vincular ?></div>
        </div>
    </div>
</div>

<!-- Filtros rápidos -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <input type="text" id="filtro-nombre" class="form-control form-control-sm" style="max-width:280px;"
           placeholder="Filtrar por nombre o SKU…">
    <select id="filtro-estado" class="form-select form-select-sm" style="max-width:180px;">
        <option value="">Todos</option>
        <option value="cambio">Con cambio de precio</option>
        <option value="nuevo">Nuevos (sin precio anterior)</option>
        <option value="sin-vincular">Sin vincular</option>
    </select>
</div>

<!-- Tabla de productos -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tabla-productos">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>SKU / EAN</th>
                        <th class="text-end">Precio normal</th>
                        <th class="text-end">Precio anterior</th>
                        <th class="text-end">Variación</th>
                        <th class="text-end">Precio oferta</th>
                        <th class="text-center">Dispon.</th>
                        <th class="text-end">Precio Tecnofoto</th>
                        <th>Catálogo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            No hay productos en este run.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productos as $p):
                        $diff       = calcDiff((int)$p['precio_normal'], isset($p['precio_normal_anterior']) ? (int)$p['precio_normal_anterior'] : null);
                        $esNuevo    = $p['precio_normal_anterior'] === null;
                        $esCambio   = !$esNuevo && $diff !== null;
                        $vinculado  = !empty($p['producto_id']);

                        $rowClass = '';
                        if ($esCambio) $rowClass = 'table-warning';
                        if ($esNuevo && !$vinculado) $rowClass = '';
                    ?>
                    <tr class="<?= $rowClass ?>"
                        data-nombre="<?= esc(strtolower($p['nombre'] . ' ' . $p['sku'] . ' ' . $p['ean'])) ?>"
                        data-cambio="<?= $esCambio ? '1' : '0' ?>"
                        data-nuevo="<?= $esNuevo ? '1' : '0' ?>"
                        data-vinculado="<?= $vinculado ? '1' : '0' ?>">

                        <td>
                            <?php if ($p['url']): ?>
                                <a href="<?= esc($p['url']) ?>" target="_blank" rel="noopener"
                                   class="text-decoration-none text-body">
                                    <?= esc($p['nombre']) ?>
                                    <i class="bi bi-box-arrow-up-right text-muted ms-1" style="font-size:.7rem;"></i>
                                </a>
                            <?php else: ?>
                                <?= esc($p['nombre']) ?>
                            <?php endif; ?>
                        </td>

                        <td class="text-muted small">
                            <?php if ($p['sku']): ?>
                                <span class="badge bg-light text-dark border"><?= esc($p['sku']) ?></span>
                            <?php endif; ?>
                            <?php if ($p['ean']): ?>
                                <span class="badge bg-light text-dark border"><?= esc($p['ean']) ?></span>
                            <?php endif; ?>
                            <?php if (!$p['sku'] && !$p['ean']): ?>—<?php endif; ?>
                        </td>

                        <td class="text-end fw-semibold">
                            <?= fmtClp(isset($p['precio_normal']) ? (int)$p['precio_normal'] : null) ?>
                        </td>

                        <td class="text-end text-muted small">
                            <?= fmtClp(isset($p['precio_normal_anterior']) && $p['precio_normal_anterior'] !== null ? (int)$p['precio_normal_anterior'] : null) ?>
                        </td>

                        <td class="text-end small">
                            <?php if ($esNuevo): ?>
                                <span class="badge bg-primary">Nuevo</span>
                            <?php elseif ($diff): ?>
                                <span class="fw-semibold <?= $diff['class'] ?>"><?= $diff['pct'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-end text-muted small">
                            <?= fmtClp(isset($p['precio_oferta']) ? (int)$p['precio_oferta'] : null) ?>
                        </td>

                        <td class="text-center">
                            <?php if ($p['disponible']): ?>
                                <i class="bi bi-check-circle-fill text-success" title="Disponible"></i>
                            <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger" title="Sin stock"></i>
                            <?php endif; ?>
                        </td>

                        <td class="text-end small">
                            <?php if ($vinculado && isset($p['precio_tecnofoto'])): ?>
                                <span class="fw-semibold"><?= fmtClp((int)$p['precio_tecnofoto']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="small">
                            <?php if ($vinculado): ?>
                                <a href="<?= site_url('productos/edit/' . $p['producto_id']) ?>"
                                   class="text-decoration-none" target="_blank">
                                    <i class="bi bi-box me-1 text-primary"></i><?= esc($p['producto_nombre'] ?? '#' . $p['producto_id']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Sin vincular</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-outline-primary btn-action btn-vincular"
                                    data-id="<?= $p['id'] ?>"
                                    data-nombre="<?= esc($p['nombre']) ?>"
                                    data-run="<?= $run['id'] ?>"
                                    data-actual="<?= $p['producto_id'] ?? '' ?>"
                                    title="Vincular a catálogo">
                                <i class="bi bi-link-45deg"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Categorías scrapeadas -->
<?php if (!empty($categorias)): ?>
<div class="modal fade" id="modalCategorias" tabindex="-1" aria-labelledby="modalCategoriasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="modalCategoriasLabel">
                    <i class="bi bi-list-ul me-2 text-primary"></i>Categorías scrapeadas
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($categorias as $cat): ?>
                    <li class="list-group-item d-flex align-items-start gap-2 py-2">
                        <i class="bi bi-tag text-muted mt-1" style="font-size:.85rem;"></i>
                        <div>
                            <div class="fw-semibold small"><?= esc($cat['nombre']) ?></div>
                            <a href="<?= esc($cat['url']) ?>" target="_blank" rel="noopener"
                               class="text-muted small text-decoration-none text-break">
                                <?= esc($cat['url']) ?>
                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.65rem;"></i>
                            </a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Vincular producto -->
<div class="modal fade" id="modalVincular" tabindex="-1" aria-labelledby="modalVincularLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="modalVincularLabel">
                    <i class="bi bi-link-45deg me-2 text-primary"></i>Vincular al catálogo
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= site_url('scrapers/link') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="run_id" value="<?= $run['id'] ?>">
                <input type="hidden" name="scraper_producto_id" id="modal-scraper-id">
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Vinculando: <strong id="modal-nombre-producto" class="text-body"></strong>
                    </p>

                    <!-- Búsqueda en el select -->
                    <div class="mb-2">
                        <input type="text" id="buscar-catalogo" class="form-control form-control-sm"
                               placeholder="Buscar por nombre o SKU…" autocomplete="off">
                    </div>

                    <select name="producto_id" id="select-catalogo"
                            class="form-select form-select-sm" size="8" required>
                        <?php foreach ($catalogo_options as $opt): ?>
                            <option value="<?= $opt['id'] ?>"><?= esc($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Haz clic en un producto para seleccionarlo.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-link-45deg me-1"></i>Vincular
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// --- Vincular modal ---
const modal        = new bootstrap.Modal(document.getElementById('modalVincular'));
const modalScrapId = document.getElementById('modal-scraper-id');
const modalNombre  = document.getElementById('modal-nombre-producto');
const selectCat    = document.getElementById('select-catalogo');
const buscarInput  = document.getElementById('buscar-catalogo');

// Guardar todas las opciones para restaurar al limpiar el filtro
const todasOpciones = [...selectCat.options];

document.querySelectorAll('.btn-vincular').forEach(btn => {
    btn.addEventListener('click', () => {
        modalScrapId.value   = btn.dataset.id;
        modalNombre.textContent = btn.dataset.nombre;

        // Pre-seleccionar el producto ya vinculado (si existe)
        const actual = btn.dataset.actual;
        if (actual) {
            selectCat.value = actual;
        } else {
            selectCat.selectedIndex = -1;
        }

        buscarInput.value = '';
        filtrarOpciones('');
        modal.show();
        setTimeout(() => buscarInput.focus(), 300);
    });
});

buscarInput.addEventListener('input', () => filtrarOpciones(buscarInput.value));

function filtrarOpciones(q) {
    const term = q.toLowerCase().trim();
    // Reconstruir opciones visibles
    while (selectCat.options.length) selectCat.remove(0);
    todasOpciones
        .filter(o => !term || o.text.toLowerCase().includes(term))
        .forEach(o => selectCat.add(new Option(o.text, o.value)));
    if (selectCat.options.length === 1) {
        selectCat.selectedIndex = 0;
    }
}

// --- Filtro de tabla ---
const filtrNombre = document.getElementById('filtro-nombre');
const filtrEstado = document.getElementById('filtro-estado');
const filas       = document.querySelectorAll('#tabla-productos tbody tr[data-nombre]');

function aplicarFiltros() {
    const q      = filtrNombre.value.toLowerCase().trim();
    const estado = filtrEstado.value;

    filas.forEach(tr => {
        const matchNombre = !q || tr.dataset.nombre.includes(q);
        const matchEstado = !estado
            || (estado === 'cambio'       && tr.dataset.cambio    === '1')
            || (estado === 'nuevo'        && tr.dataset.nuevo     === '1')
            || (estado === 'sin-vincular' && tr.dataset.vinculado === '0');

        tr.style.display = (matchNombre && matchEstado) ? '' : 'none';
    });
}

filtrNombre.addEventListener('input',  aplicarFiltros);
filtrEstado.addEventListener('change', aplicarFiltros);
</script>
<?= $this->endSection() ?>
