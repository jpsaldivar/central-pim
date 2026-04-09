<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">Listado de Categorías</h6>
            <a href="/categorias/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nueva Categoría
            </a>
        </div>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Categoría Padre</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($categorias)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No hay categorías registradas.</td></tr>
            <?php else: ?>
                <?php
                $catMap = [];
                foreach ($categorias as $c) { $catMap[$c['id']] = $c['nombre']; }
                foreach ($categorias as $c):
                ?>
                <tr>
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
<?= $this->endSection() ?>
