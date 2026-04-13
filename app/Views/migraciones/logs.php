<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card">
    <div class="card-body p-0">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <div>
                <h6 class="fw-semibold mb-0">
                    <i class="bi bi-journal-text me-2"></i>Historial de Migración
                </h6>
                <small class="text-muted"><?= number_format($total) ?> registros totales</small>
            </div>
            <a href="<?= site_url('migraciones') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>SKU</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Acción</th>
                        <th>Estado</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Sin registros.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-nowrap small text-muted"><?= esc($log['created_at']) ?></td>
                        <td><code class="small"><?= esc($log['sku']) ?></code></td>
                        <td class="small"><?= esc(mb_substr($log['nombre_producto'], 0, 50)) ?></td>
                        <td class="small text-muted"><?= esc($log['tipo']) ?></td>
                        <td><span class="badge bg-secondary"><?= esc($log['accion']) ?></span></td>
                        <td>
                            <?php
                            $badgeClass = match($log['estado']) {
                                'success' => 'success',
                                'error'   => 'danger',
                                'warning' => 'warning',
                                default   => 'secondary',
                            };
                            ?>
                            <span class="badge bg-<?= $badgeClass ?>">
                                <?= esc($log['estado']) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= esc($log['mensaje']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="p-3 border-top d-flex gap-2 justify-content-center">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a
                    href="?page=<?= $p ?>"
                    class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
                >
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
