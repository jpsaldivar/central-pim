<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
    $payload_recibido = json_decode($documento['payload_recibido'] ?? '{}', true) ?? [];
    $payload_enviado  = json_decode($documento['payload_enviado']  ?? '{}', true) ?? [];
    $bsale_response   = json_decode($documento['bsale_response']   ?? '{}', true) ?? [];
    $email            = $payload_recibido['client_email'] ?? '—';
    $lineItems        = $payload_recibido['items'] ?? [];
?>

<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <a href="/bsale" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver al dashboard
    </a>

    <?php if (in_array($documento['estado'], ['pendiente', 'error'])): ?>
        <?php if ($boletaTypeId > 0): ?>
        <form method="POST" action="/bsale/emitir/<?= $documento['id'] ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="document_type_id" value="<?= $boletaTypeId ?>">
            <button type="submit" class="btn btn-sm btn-primary"
                    onclick="return confirm('¿Emitir boleta en Bsale para este pedido?')">
                <i class="bi bi-receipt me-1"></i>Emitir boleta
            </button>
        </form>
        <?php endif; ?>

        <?php if ($facturaTypeId > 0): ?>
        <form method="POST" action="/bsale/emitir/<?= $documento['id'] ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="document_type_id" value="<?= $facturaTypeId ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary"
                    onclick="return confirm('¿Emitir factura en Bsale para este pedido?')">
                <i class="bi bi-file-earmark-text me-1"></i>Emitir factura
            </button>
        </form>
        <?php endif; ?>

        <?php if ($boletaTypeId === 0 && $facturaTypeId === 0): ?>
        <div class="alert alert-warning py-1 px-3 mb-0 small">
            Configure <code>BSALE_BOLETA_TYPE_ID</code> y/o <code>BSALE_FACTURA_TYPE_ID</code> en <code>.env</code> para habilitar la emisión.
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="row g-4">

    <!-- Resumen del pedido -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header py-3 fw-semibold">Pedido WooCommerce</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted small">Pedido #</dt>
                    <dd class="col-sm-7"><?= esc($documento['woo_order_id']) ?></dd>

                    <dt class="col-sm-5 text-muted small">Email cliente</dt>
                    <dd class="col-sm-7"><?= esc($email) ?></dd>

                    <dt class="col-sm-5 text-muted small">Estado</dt>
                    <dd class="col-sm-7">
                        <?php if ($documento['estado'] === 'creado'): ?>
                            <span class="badge bg-success">creado</span>
                        <?php elseif ($documento['estado'] === 'error'): ?>
                            <span class="badge bg-danger">error</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">pendiente</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-5 text-muted small">Doc. Bsale ID</dt>
                    <dd class="col-sm-7"><?= $documento['bsale_document_id'] ? esc($documento['bsale_document_id']) : '—' ?></dd>

                    <dt class="col-sm-5 text-muted small">Creado</dt>
                    <dd class="col-sm-7"><?= esc(date('d/m/Y H:i', strtotime($documento['created_at']))) ?></dd>

                    <?php if ($documento['bsale_document_url']): ?>
                    <dt class="col-sm-5 text-muted small">PDF</dt>
                    <dd class="col-sm-7">
                        <a href="<?= esc($documento['bsale_document_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-file-pdf me-1"></i>Ver PDF
                        </a>
                    </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($documento['mensaje_error']): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            <strong>Error:</strong> <?= esc($documento['mensaje_error']) ?>
        </div>
        <?php endif; ?>

        <!-- Ítems del pedido -->
        <?php if (!empty($lineItems)): ?>
        <div class="card">
            <div class="card-header py-3 fw-semibold">Ítems del pedido</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Producto</th><th>SKU</th><th>Qty</th><th>Precio</th></tr></thead>
                    <tbody>
                    <?php foreach ($lineItems as $item): ?>
                        <tr>
                            <td class="small"><?= esc($item['name'] !== '' ? $item['name'] : '—') ?></td>
                            <td><code class="small"><?= esc($item['sku'] ?? '—') ?></code></td>
                            <td><?= esc($item['quantity'] ?? 1) ?></td>
                            <td>$<?= number_format((float)($item['unit_price'] ?? 0), 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payloads JSON -->
    <div class="col-lg-7">

        <!-- Payload enviado a Bsale -->
        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Payload enviado a Bsale</span>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#payload-enviado">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="collapse show" id="payload-enviado">
                <div class="card-body p-0">
                    <pre class="m-0 p-3 bg-light small" style="max-height:300px;overflow:auto;border-radius:0 0 .5rem .5rem"><?= empty($payload_enviado) ? 'No disponible' : esc(json_encode($payload_enviado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
            </div>
        </div>

        <!-- Respuesta de Bsale -->
        <div class="card mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Respuesta de Bsale</span>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#bsale-response">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="collapse" id="bsale-response">
                <div class="card-body p-0">
                    <pre class="m-0 p-3 bg-light small" style="max-height:300px;overflow:auto;border-radius:0 0 .5rem .5rem"><?= empty($bsale_response) ? 'No disponible' : esc(json_encode($bsale_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
            </div>
        </div>

        <!-- Payload recibido de WooCommerce -->
        <div class="card">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Payload recibido de WooCommerce</span>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#payload-recibido">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="collapse" id="payload-recibido">
                <div class="card-body p-0">
                    <pre class="m-0 p-3 bg-light small" style="max-height:400px;overflow:auto;border-radius:0 0 .5rem .5rem"><?= esc(json_encode($payload_recibido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
