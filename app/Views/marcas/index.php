<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">Listado de Marcas</h6>
            <a href="/marcas/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nueva Marca
            </a>
        </div>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($marcas)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">No hay marcas registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($marcas as $m): ?>
                <tr>
                    <td class="text-muted"><?= $m['id'] ?></td>
                    <td><?= esc($m['nombre']) ?></td>
                    <td class="text-end">
                        <a href="/marcas/edit/<?= $m['id'] ?>" class="btn btn-outline-secondary btn-action me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/marcas/delete/<?= $m['id'] ?>" class="btn btn-outline-danger btn-action"
                           onclick="return confirm('¿Eliminar esta marca?')">
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
