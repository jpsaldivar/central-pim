<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$canRun   = $jumpseller_ok && $woocommerce_ok;
$hasState = !empty($migration_state) && ($migration_state['status'] === 'in_progress');
$initPct  = $hasState
    ? (int)round(($migration_state['last_completed_page'] / max($migration_state['total_pages'], 1)) * 100)
    : 0;
?>

<!-- Bloque de progreso en vivo (visible si hay checkpoint activo) -->
<div id="progreso-bloque" class="card mb-4 <?= $hasState ? '' : 'd-none' ?>">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="fw-semibold mb-0">
                <span id="progreso-icono" class="me-2">
                    <?= $hasState ? '<i class="bi bi-arrow-repeat text-primary" id="spin-icon"></i>' : '' ?>
                </span>
                <span id="progreso-titulo">Migración en curso</span>
            </h6>
            <small class="text-muted">
                Última actualización: <span id="progreso-ultima"><?= $hasState ? esc($migration_state['last_update'] ?? '—') : '—' ?></span>
            </small>
        </div>

        <div class="progress mb-2" style="height:10px;">
            <div id="progreso-barra"
                 class="progress-bar progress-bar-striped progress-bar-animated"
                 style="width:<?= $initPct ?>%">
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
                Página <strong><span id="progreso-pagina"><?= $hasState ? $migration_state['last_completed_page'] : 0 ?></span></strong>
                de <strong><span id="progreso-total"><?= $hasState ? $migration_state['total_pages'] : 0 ?></span></strong>
                &nbsp;·&nbsp;
                Creados: <span id="progreso-creados"><?= $hasState ? ($migration_state['summary']['created'] ?? 0) : 0 ?></span>
                &nbsp;·&nbsp;
                Actualizados: <span id="progreso-actualizados"><?= $hasState ? ($migration_state['summary']['updated'] ?? 0) : 0 ?></span>
                &nbsp;·&nbsp;
                Errores: <span id="progreso-errores"><?= $hasState ? ($migration_state['summary']['errors'] ?? 0) : 0 ?></span>
            </small>
            <strong id="progreso-pct" class="text-primary"><?= $initPct ?>%</strong>
        </div>
    </div>
</div>

<!-- Banner: migración pausada (sin proceso activo) -->
<div id="banner-pausado" class="<?= $hasState ? '' : 'd-none' ?>">
    <!-- se muestra cuando el polling detecta que el proceso se detuvo -->
</div>

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
                    <small class="text-danger">Faltan <code>woocommerce.url</code> / keys en .env</small>
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
            Importa todos los productos de Jumpseller hacia WooCommerce aplicando upsert por SKU.
            Los productos simples se envían en lotes de 50; los variables se crean junto con sus variantes.
            Si el proceso se interrumpe, el progreso queda guardado y puedes continuar desde aquí.
        </p>

        <div id="aviso-estado" class="alert py-2 small mb-3 <?= $hasState ? 'alert-info' : 'alert-warning' ?>">
            <?php if ($hasState): ?>
                <i class="bi bi-info-circle me-1"></i>
                Hay una migración en curso o pausada. Espera a que termine o usa los botones de abajo.
            <?php else: ?>
                <i class="bi bi-exclamation-triangle me-1"></i>
                Si el hosting interrumpe el proceso, el progreso queda guardado y podrás continuar.
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <form action="<?= site_url('migraciones/ejecutar') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary" id="btn-iniciar"
                    <?= (!$canRun || $hasState) ? 'disabled' : '' ?>
                    onclick="return confirm('¿Iniciar migración desde el principio?');">
                    <i class="bi bi-play-fill me-2"></i>Iniciar
                </button>
            </form>

            <form action="<?= site_url('migraciones/reanudar') ?>" method="post" id="form-reanudar">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-warning" id="btn-reanudar"
                    <?= (!$canRun || !$hasState) ? 'disabled' : '' ?>>
                    <i class="bi bi-play-fill me-2"></i>Continuar
                </button>
            </form>

            <form action="<?= site_url('migraciones/reiniciar') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary" id="btn-reiniciar"
                    <?= !$hasState ? 'disabled' : '' ?>
                    onclick="return confirm('¿Descartar el progreso y empezar de cero?');">
                    <i class="bi bi-x-circle me-1"></i>Descartar progreso
                </button>
            </form>

            <a href="<?= site_url('migraciones/logs') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-journal-text me-2"></i>Ver logs
            </a>
        </div>
    </div>
</div>

<!-- Recent Logs -->
<?php if (!empty($recent_logs)): ?>
<div class="card">
    <div class="card-body p-0">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <h6 class="fw-semibold mb-0"><i class="bi bi-list-ul me-2"></i>Actividad Reciente</h6>
            <a href="<?= site_url('migraciones/logs') ?>" class="btn btn-sm btn-outline-secondary">Ver todo</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th><th>SKU</th><th>Producto</th><th>Acción</th><th>Estado</th><th>Mensaje</th>
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
                            <span class="badge bg-<?= match($log['estado']) {
                                'success' => 'success', 'error' => 'danger',
                                'warning' => 'warning', default  => 'secondary'
                            } ?>"><?= esc($log['estado']) ?></span>
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

<?= $this->section('scripts') ?>
<script>
const PROGRESO_URL  = '<?= site_url('migraciones/progreso') ?>';
const POLL_INTERVAL = 20000; // 20 segundos
let   pollTimer     = null;
let   lastPage      = <?= $hasState ? $migration_state['last_completed_page'] : -1 ?>;

function actualizarUI(data) {
    const bloque = document.getElementById('progreso-bloque');
    const barra  = document.getElementById('progreso-barra');

    if (!data.active) {
        // Proceso terminado o pausado — recarga para mostrar estado final
        clearInterval(pollTimer);
        document.getElementById('progreso-titulo').textContent = 'Migración completada o pausada';
        barra.classList.remove('progress-bar-animated', 'progress-bar-striped', 'bg-primary');
        barra.classList.add('bg-success');
        setTimeout(() => location.reload(), 2000);
        return;
    }

    // Actualizar barra y cifras
    bloque.classList.remove('d-none');
    barra.style.width = data.percent + '%';
    document.getElementById('progreso-pct').textContent          = data.percent + '%';
    document.getElementById('progreso-pagina').textContent       = data.last_completed_page;
    document.getElementById('progreso-total').textContent        = data.total_pages;
    document.getElementById('progreso-creados').textContent      = data.summary.created    ?? 0;
    document.getElementById('progreso-actualizados').textContent = data.summary.updated    ?? 0;
    document.getElementById('progreso-errores').textContent      = data.summary.errors     ?? 0;
    document.getElementById('progreso-ultima').textContent       = data.last_update        ?? '—';

    // Si la página avanzó respecto al valor anterior, el proceso sigue vivo
    if (data.last_completed_page !== lastPage) {
        lastPage = data.last_completed_page;
    }
}

function poll() {
    fetch(PROGRESO_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(actualizarUI)
        .catch(() => { /* red caída — reintentará en el próximo intervalo */ });
}

// Arrancar polling si hay estado activo al cargar la página
if (<?= $hasState ? 'true' : 'false' ?>) {
    pollTimer = setInterval(poll, POLL_INTERVAL);
}
</script>
<?= $this->endSection() ?>
