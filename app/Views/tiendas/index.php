<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">Listado de Tiendas</h6>
            <a href="/tiendas/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nueva Tienda
            </a>
        </div>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Plataforma</th>
                    <th>URL API</th>
                    <th>Token</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($tiendas)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay tiendas registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($tiendas as $t): ?>
                <tr>
                    <td class="text-muted"><?= $t['id'] ?></td>
                    <td><?= esc($t['nombre']) ?></td>
                    <td><span class="badge bg-secondary text-capitalize"><?= esc($t['plataforma'] ?? '—') ?></span></td>
                    <td><code class="small"><?= esc($t['url_api']) ?></code></td>
                    <td><code class="small text-muted"><?= substr(esc($t['token_auth']), 0, 20) ?>…</code></td>
                    <td class="text-end">
                        <a href="/tiendas/edit/<?= $t['id'] ?>" class="btn btn-outline-secondary btn-action me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/tiendas/delete/<?= $t['id'] ?>" class="btn btn-outline-danger btn-action"
                           onclick="return confirm('¿Eliminar esta tienda?')">
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
