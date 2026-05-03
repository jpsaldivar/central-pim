<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<form method="POST" action="<?= site_url('actualizaciones/stock') ?>" id="form-stock">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">
                    Edita el stock de los productos de esta página y guarda al terminar.
                    El stock específico por tienda sobreescribe el general para esa tienda; déjalo vacío para usar el general.
                </span>
                <span id="dirty-badge" class="badge bg-warning text-dark d-none">
                    <i class="bi bi-pencil me-1"></i>Cambios sin guardar
                </span>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-floppy me-1"></i>Guardar cambios
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div style="overflow-x:auto;">
                <table class="table table-sm table-hover align-middle mb-0" style="min-width:400px;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="min-width:180px;">Producto</th>
                            <th class="text-center border-start" style="min-width:110px;">Stock general</th>
                            <th class="text-center border-start" style="min-width:80px;" title="Stock sin límite">&#8734;</th>
                            <?php foreach ($tiendas as $t): ?>
                            <th class="text-center border-start" style="min-width:110px;">
                                <?= esc($t['nombre']) ?>
                            </th>
                            <?php endforeach ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="<?= 3 + count($tiendas) ?>" class="text-center text-muted py-4">
                                No hay productos.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                        <tr>
                            <td class="ps-3">
                                <span class="fw-medium" style="font-size:.85rem;">
                                    <?= esc($p['nombre']) ?>
                                </span>
                                <br>
                                <small class="text-muted">#<?= $p['id'] ?></small>
                            </td>
                            <!-- Stock general -->
                            <?php $esIlimitado = !empty($p['stock_ilimitado']); ?>
                            <td class="border-start">
                                <input type="number" min="0" step="1"
                                       class="form-control form-control-sm editable-input stock-qty-input"
                                       name="stock_general[<?= $p['id'] ?>]"
                                       value="<?= $p['stock_general'] ?>"
                                       <?= $esIlimitado ? 'readonly' : '' ?>>
                            </td>
                            <!-- Ilimitado -->
                            <td class="border-start text-center">
                                <input type="checkbox"
                                       class="form-check-input ilimitado-check editable-input"
                                       name="stock_ilimitado[<?= $p['id'] ?>]"
                                       value="1"
                                       <?= $esIlimitado ? 'checked' : '' ?>>
                            </td>
                            <!-- Por tienda -->
                            <?php foreach ($tiendas as $t): ?>
                            <?php $pt = $producto_tiendas[$p['id']][$t['id']] ?? null; ?>
                            <?php if ($pt): ?>
                            <td class="border-start">
                                <input type="number" min="0" step="1"
                                       class="form-control form-control-sm editable-input"
                                       name="stock_esp[<?= $p['id'] ?>][<?= $t['id'] ?>]"
                                       value="<?= $pt['stock_especifico'] ?? '' ?>"
                                       placeholder="usa general">
                            </td>
                            <?php else: ?>
                            <td class="border-start text-center text-muted" title="No activo en esta tienda">
                                <small>—</small>
                            </td>
                            <?php endif ?>
                            <?php endforeach ?>
                        </tr>
                        <?php endforeach ?>
                    <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginación y selector -->
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="d-flex align-items-center gap-2">
            <?php if ($pager): ?>
            <small class="text-muted">
                Página <?= $pager->getCurrentPage() ?> de <?= $pager->getPageCount() ?>
            </small>
            <?php endif ?>
            <select class="form-select form-select-sm" style="width:auto;"
                    onchange="cambiarPorPagina(this.value)">
                <?php foreach ([25, 50, 100, 500, 1000] as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>>
                    <?= $opt ?> por página
                </option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($pager && $pager->getPageCount() > 1): ?>
            <?= $pager->links('default', 'bootstrap5') ?>
            <?php endif ?>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-floppy me-1"></i>Guardar cambios
            </button>
        </div>
    </div>

</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let dirty = false;

document.querySelectorAll('.editable-input').forEach(input => {
    input.addEventListener('input', () => {
        dirty = true;
        document.getElementById('dirty-badge').classList.remove('d-none');
    });
    input.addEventListener('change', () => {
        dirty = true;
        document.getElementById('dirty-badge').classList.remove('d-none');
    });
});

document.querySelectorAll('.ilimitado-check').forEach(chk => {
    chk.addEventListener('change', function() {
        var row   = this.closest('tr');
        var input = row.querySelector('.stock-qty-input');
        if (this.checked) {
            input.value    = 0;
            input.readOnly = true;
            input.classList.add('text-muted', 'bg-light');
        } else {
            input.readOnly = false;
            input.classList.remove('text-muted', 'bg-light');
        }
    });
});

document.getElementById('form-stock').addEventListener('submit', () => {
    dirty = false;
});

window.addEventListener('beforeunload', e => {
    if (dirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});

function cambiarPorPagina(value) {
    if (dirty && !confirm('Hay cambios sin guardar. ¿Cambiar de página de todas formas?')) return;
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}
</script>
<?= $this->endSection() ?>
