<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Formulario oculto para eliminación masiva -->
<form id="bulk-form" action="<?= site_url('categorias/bulk') ?>" method="POST">
    <?= csrf_field() ?>
</form>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">Listado de Categorías</h6>
            <a href="/categorias/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nueva Categoría
            </a>
        </div>

        <!-- Barra de acciones masivas -->
        <div id="bulk-bar" class="d-none alert alert-secondary py-2 px-3 mb-3 d-flex align-items-center gap-3">
            <span class="text-muted small">
                <strong><span id="bulk-count">0</span></strong> categoría(s) seleccionada(s)
            </span>
            <div class="d-flex gap-2 ms-auto">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="abrirModalEliminar()">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
            </div>
        </div>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:36px;">
                        <input type="checkbox" id="check-all" class="form-check-input">
                    </th>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Categoría Padre</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($categorias)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay categorías registradas.</td></tr>
            <?php else: ?>
                <?php
                $catMap = [];
                foreach ($categorias as $c) { $catMap[$c['id']] = $c['nombre']; }
                foreach ($categorias as $c):
                ?>
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input row-check" value="<?= $c['id'] ?>">
                    </td>
                    <td class="text-muted"><?= $c['id'] ?></td>
                    <td><?= esc($c['nombre']) ?></td>
                    <td class="text-muted small"><?= esc($c['descripcion'] ?? '-') ?></td>
                    <td>
                        <?= $c['parent_id'] ? '<span class="badge bg-light text-dark">' . esc($catMap[$c['parent_id']] ?? '?') . '</span>' : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="text-end">
                        <a href="/categorias/edit/<?= $c['id'] ?>" class="btn btn-outline-secondary btn-action me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/categorias/delete/<?= $c['id'] ?>" class="btn btn-outline-danger btn-action"
                           onclick="return confirm('¿Eliminar esta categoría? Los hijos quedarán sin padre.')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Eliminar categorías -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-danger">
                <h6 class="modal-title fw-semibold text-danger" id="modalEliminarLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Eliminar Categorías
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    Estás a punto de eliminar permanentemente
                    <strong><span id="modal-count">0</span></strong> categoría(s) de la base de datos local.
                </p>
                <p class="text-danger small mb-0">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    Esta acción no se puede deshacer. Las subcategorías quedarán sin categoría padre.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmarEliminar()">
                    <i class="bi bi-trash me-1"></i>Eliminar definitivamente
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
    document.getElementById('modal-count').textContent = n;

    const total = document.querySelectorAll('.row-check').length;
    checkAll.indeterminate = n > 0 && n < total;
    checkAll.checked       = n > 0 && n === total;
}

checkAll.addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
    actualizarBarra();
});

document.querySelectorAll('.row-check').forEach(c => {
    c.addEventListener('change', actualizarBarra);
});

function abrirModalEliminar() {
    if (getCheckedIds().length === 0) return;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEliminar')).show();
}

function confirmarEliminar() {
    const form = document.getElementById('bulk-form');
    form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());

    getCheckedIds().forEach(id => {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });

    bootstrap.Modal.getInstance(document.getElementById('modalEliminar')).hide();
    form.submit();
}
</script>
<?= $this->endSection() ?>
