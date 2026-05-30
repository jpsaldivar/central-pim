<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (!$configurado): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Módulo no configurado.</strong> Defina <code>BSALE_ACCESS_TOKEN</code>, <code>BSALE_OFFICE_ID</code> y <code>BSALE_DOCUMENT_TYPE_ID</code> en el archivo <code>.env</code>.
</div>
<?php endif; ?>

<!-- Estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card blue p-3">
            <div class="text-muted small">Documentos hoy</div>
            <div class="fs-4 fw-bold"><?= $stats['hoy'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card teal p-3">
            <div class="text-muted small">Últimos 7 días</div>
            <div class="fs-4 fw-bold"><?= $stats['semana'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card green p-3">
            <div class="text-muted small">Creados (total)</div>
            <div class="fs-4 fw-bold"><?= $stats['creados_total'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card orange p-3">
            <div class="text-muted small">Errores pendientes</div>
            <div class="fs-4 fw-bold text-danger"><?= $stats['errores_total'] ?></div>
        </div>
    </div>
</div>

<!-- Tabla de documentos -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-3">
        <span class="fw-semibold">Últimos documentos</span>
        <a href="/bsale/variant-map" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapeo de variantes
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($documentos)): ?>
            <p class="text-muted text-center py-4 mb-0">Sin documentos registrados aún.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Pedido WC</th>
                        <th>Email cliente</th>
                        <th>Estado</th>
                        <th>Doc. Bsale</th>
                        <th>PDF</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($documentos as $doc): ?>
                    <?php
                        $payload = json_decode($doc['payload_recibido'], true) ?? [];
                        $email   = $payload['client_email'] ?? '—';
                    ?>
                    <tr>
                        <td class="text-muted small"><?= $doc['id'] ?></td>
                        <td><strong>#<?= esc($doc['woo_order_id']) ?></strong></td>
                        <td><?= esc($email) ?></td>
                        <td>
                            <?php if ($doc['estado'] === 'creado'): ?>
                                <span class="badge bg-success">creado</span>
                            <?php elseif ($doc['estado'] === 'error'): ?>
                                <span class="badge bg-danger">error</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $doc['bsale_document_id'] ? esc($doc['bsale_document_id']) : '—' ?></td>
                        <td>
                            <?php if ($doc['bsale_document_url']): ?>
                                <a href="<?= esc($doc['bsale_document_url']) ?>" target="_blank" class="btn-action btn btn-outline-primary btn-sm">
                                    <i class="bi bi-file-pdf"></i>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= esc(date('d/m/Y H:i', strtotime($doc['created_at']))) ?></td>
                        <td>
                            <a href="/bsale/show/<?= $doc['id'] ?>" class="btn-action btn btn-outline-secondary btn-sm"
                               title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (in_array($doc['estado'], ['pendiente', 'error'])): ?>
                                <a href="/bsale/show/<?= $doc['id'] ?>" class="btn-action btn btn-outline-primary btn-sm"
                                   title="Emitir documento">
                                    <i class="bi bi-send"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
