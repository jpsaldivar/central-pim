<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Configuration Status -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card p-3 h-100">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-plug me-2 text-primary"></i>Estado de Conexiones
            </h6>
            <div class="d-flex align-items-center gap-2 mb-2">
                <?php if ($jumpseller_ok): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Jumpseller</span>
                    <small class="text-muted">Credenciales configuradas</small>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Jumpseller</span>
                    <small class="text-danger">Faltan <code>jumpseller.login</code> / <code>jumpseller.authtoken</code> en .env</small>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($woocommerce_ok): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>WooCommerce</span>
                    <small class="text-muted">Credenciales configuradas</small>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>WooCommerce</span>
                    <small class="text-danger">Faltan <code>woocommerce.url</code> / <code>woocommerce.consumer_key</code> / <code>woocommerce.consumer_secret</code> en .env</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card p-3 h-100">
            <h6 class="fw-semibold mb-3">
                <i class="bi bi-bar-chart me-2 text-info"></i>Última Migración
            </h6>
            <?php if (!empty($last_session_stats['started_at'])): ?>
                <div class="small text-muted mb-2">
                    <i class="bi bi-clock me-1"></i><?= esc($last_session_stats['started_at']) ?>
                </div>
                <div class="d-flex gap-3">
                    <div class="text-center">
                        <div class="fs-5 fw-bold text-success"><?= $last_session_stats['success'] ?></div>
                        <small class="text-muted">Exitosos</small>
                    </div>
                    <div class="text-center">
                        <div class="fs-5 fw-bold text-danger"><?= $last_session_stats['error'] ?></div>
                        <small class="text-muted">Errores</small>
                    </div>
                    <div class="text-center">
                        <div class="fs-5 fw-bold text-warning"><?= $last_session_stats['warning'] ?></div>
                        <small class="text-muted">Advertencias</small>
                    </div>
                    <div class="text-center">
                        <div class="fs-5 fw-bold text-secondary"><?= $last_session_stats['info'] ?></div>
                        <small class="text-muted">Info</small>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-0">Aún no se ha ejecutado ninguna migración.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Migration Trigger -->
<div class="card mb-4">
    <div class="card-body p-4">
        <h6 class="fw-semibold mb-1">
            <i class="bi bi-arrow-left-right me-2 text-primary"></i>Migración Jumpseller → WooCommerce
        </h6>
        <p class="text-muted small mb-3">
            Importa todos los productos de Jumpseller hacia WooCommerce aplicando la lógica de upsert por SKU.
            Los productos simples se envían en lotes de 50; los productos variables se crean junto con sus variantes.
        </p>

        <div class="alert alert-warning py-2 small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Esta operación puede tardar varios minutos dependiendo del tamaño del catálogo.
            No cierre esta ventana hasta ver la confirmación.
        </div>

        <form action="<?= site_url('migraciones/ejecutar') ?>" method="post">
            <?= csrf_field() ?>
            <button
                type="submit"
                class="btn btn-primary"
                <?= (!$jumpseller_ok || !$woocommerce_ok) ? 'disabled' : '' ?>
                onclick="return confirm('¿Confirmar inicio de migración? Este proceso puede tardar varios minutos.');"
            >
                <i class="bi bi-play-fill me-2"></i>Ejecutar Migración
            </button>
            <a href="<?= site_url('migraciones/logs') ?>" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-journal-text me-2"></i>Ver todos los logs
            </a>
        </form>
    </div>
</div>

<!-- Recent Logs -->
<?php if (!empty($recent_logs)): ?>
<div class="card">
    <div class="card-body p-0">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <h6 class="fw-semibold mb-0">
                <i class="bi bi-list-ul me-2"></i>Actividad Reciente
            </h6>
            <a href="<?= site_url('migraciones/logs') ?>" class="btn btn-sm btn-outline-secondary">
                Ver todo
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>SKU</th>
                        <th>Producto</th>
                        <th>Acción</th>
                        <th>Estado</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                    <tr>
                        <td class="text-nowrap small text-muted"><?= esc($log['created_at']) ?></td>
                        <td><code class="small"><?= esc($log['sku']) ?></code></td>
                        <td class="small"><?= esc(mb_substr($log['nombre_producto'], 0, 40)) ?></td>
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
                        <td class="small text-muted"><?= esc(mb_substr($log['mensaje'], 0, 80)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
