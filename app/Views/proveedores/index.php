<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">Listado de Proveedores</h6>
            <a href="/proveedores/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nuevo Proveedor
            </a>
        </div>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Tiempo Encargo</th>
                    <th>Contacto</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($proveedores)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No hay proveedores registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($proveedores as $p): ?>
                <tr>
                    <td class="text-muted"><?= $p['id'] ?></td>
                    <td><?= esc($p['nombre']) ?></td>
                    <td><?= $p['tiempo_encargo'] ?> días</td>
                    <td><?= esc($p['contacto']) ?></td>
                    <td class="text-end">
                        <a href="/proveedores/edit/<?= $p['id'] ?>" class="btn btn-outline-secondary btn-action me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/proveedores/delete/<?= $p['id'] ?>" class="btn btn-outline-danger btn-action"
                           onclick="return confirm('¿Eliminar este proveedor?')">
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
