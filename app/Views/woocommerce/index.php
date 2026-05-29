<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$canRun      = $jumpseller_ok && $woocommerce_ok;
$hasState    = !empty($migration_state)  && ($migration_state['status']  === 'in_progress');
$hasInvState = !empty($inventario_state) && ($inventario_state['status'] === 'in_progress');
$hasGenState = !empty($generales_state)  && ($generales_state['status']  === 'in_progress');
$initPct     = $hasState    ? (int)round(($migration_state['last_completed_page']  / max($migration_state['total_pages'],  1)) * 100) : 0;
$initInvPct  = $hasInvState ? (int)round(($inventario_state['last_completed_page'] / max($inventario_state['total_pages'], 1)) * 100) : 0;
$initGenPct  = $hasGenState ? (int)round(($generales_state['offset'] / max($generales_state['total'], 1)) * 100) : 0;
?>

<!-- Estado de conexiones -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card p-3">
            <div class="d-flex align-items-center gap-3">
                <span class="fw-semibold small text-muted">Conexiones:</span>
                <?php if ($woocommerce_ok): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>WooCommerce</span>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>WooCommerce sin configurar</span>
                <?php endif; ?>
                <?php if ($jumpseller_ok): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Jumpseller</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Jumpseller sin configurar</span>
                <?php endif; ?>
                <a href="<?= site_url('migraciones/logs') ?>" class="btn btn-sm btn-outline-secondary ms-auto">
                    <i class="bi bi-journal-text me-1"></i>Ver logs
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ──────────────────────────────────────────────
     ACCIÓN 1 — Migración completa
     ────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-start gap-3 mb-3">
            <div class="bg-primary bg-opacity-10 rounded p-2 text-primary fs-4">
                <i class="bi bi-arrow-left-right"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">Migración completa de productos</h6>
                <p class="text-muted small mb-0">
                    Descarga todos los productos desde <strong>Jumpseller</strong> y los crea o actualiza en <strong>WooCommerce</strong>
                    aplicando upsert por SKU. Los productos simples se envían en lotes de 50; los variables se
                    crean junto con sus variantes. El proceso guarda su avance: si se interrumpe, puedes
                    retomarlo desde donde quedó sin perder nada.
                </p>
                <div class="alert alert-warning py-1 px-2 small mt-2 mb-0 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Usa esta acción cuando necesites sincronizar el catálogo completo por primera vez o tras
                    cambios masivos en Jumpseller. Para actualizaciones rutinarias de precios/stock, usa
                    "Sincronizar inventario".
                </div>
            </div>
        </div>

        <!-- Barra de progreso (visible si hay checkpoint) -->
        <div id="progreso-bloque" class="mb-3 <?= $hasState ? '' : 'd-none' ?>">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted">
                    Página <strong><span id="progreso-pagina"><?= $hasState ? $migration_state['last_completed_page'] : 0 ?></span></strong>
                    de <strong><span id="progreso-total"><?= $hasState ? $migration_state['total_pages'] : 0 ?></span></strong>
                    &nbsp;·&nbsp;Creados: <span id="progreso-creados"><?= $hasState ? ($migration_state['summary']['created'] ?? 0) : 0 ?></span>
                    &nbsp;·&nbsp;Actualizados: <span id="progreso-actualizados"><?= $hasState ? ($migration_state['summary']['updated'] ?? 0) : 0 ?></span>
                    &nbsp;·&nbsp;Errores: <span id="progreso-errores"><?= $hasState ? ($migration_state['summary']['errors'] ?? 0) : 0 ?></span>
                </small>
                <strong id="progreso-pct" class="text-primary small"><?= $initPct ?>%</strong>
            </div>
            <div class="progress" style="height:8px;">
                <div id="progreso-barra" class="progress-bar progress-bar-striped progress-bar-animated"
                     style="width:<?= $initPct ?>%"></div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <form action="<?= site_url('migraciones/ejecutar') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"
                    <?= (!$canRun || $hasState) ? 'disabled' : '' ?>
                    onclick="return confirm('¿Iniciar migración completa desde el principio? Esto puede tardar varios minutos.');">
                    <i class="bi bi-play-fill me-1"></i>Iniciar
                </button>
            </form>
            <form action="<?= site_url('migraciones/reanudar') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-warning"
                    <?= (!$canRun || !$hasState) ? 'disabled' : '' ?>>
                    <i class="bi bi-play-fill me-1"></i>Continuar
                </button>
            </form>
            <form action="<?= site_url('migraciones/reiniciar') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary"
                    <?= !$hasState ? 'disabled' : '' ?>
                    onclick="return confirm('¿Descartar el progreso guardado y empezar de cero?');">
                    <i class="bi bi-x-circle me-1"></i>Descartar progreso
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ──────────────────────────────────────────────
     ACCIÓN 2 — Sincronizar inventario
     ────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-start gap-3 mb-3">
            <div class="bg-success bg-opacity-10 rounded p-2 text-success fs-4">
                <i class="bi bi-boxes"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">Sincronizar inventario</h6>
                <p class="text-muted small mb-0">
                    Actualiza <strong>precios y stock</strong> de todos los productos vinculados a Jumpseller
                    y refleja los cambios en WooCommerce <strong>sin subir imágenes</strong>.
                    El precio original se toma del campo "compara precio" de Jumpseller; el precio de venta,
                    del campo "precio". Si un producto no tiene ID de WooCommerce registrado, se busca por
                    SKU y se vincula automáticamente. El progreso queda guardado si el proceso se interrumpe.
                </p>
                <div class="alert alert-success alert-sm py-1 px-2 small mt-2 mb-0 d-inline-flex align-items-center gap-1"
                     style="--bs-alert-bg: rgba(25,135,84,.08); border-color: rgba(25,135,84,.2);">
                    <i class="bi bi-info-circle-fill text-success"></i>
                    Ideal para actualizaciones rutinarias de precios y stock. Más rápido que la migración completa.
                </div>
            </div>
        </div>

        <!-- Barra de progreso inventario -->
        <div id="inv-progreso-bloque" class="mb-3 <?= $hasInvState ? '' : 'd-none' ?>">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted">
                    Página <strong><span id="inv-progreso-pagina"><?= $hasInvState ? $inventario_state['last_completed_page'] : 0 ?></span></strong>
                    de <strong><span id="inv-progreso-total"><?= $hasInvState ? $inventario_state['total_pages'] : 0 ?></span></strong>
                    &nbsp;·&nbsp;Actualizados: <span id="inv-progreso-actualizados"><?= $hasInvState ? ($inventario_state['summary']['updated'] ?? 0) : 0 ?></span>
                    &nbsp;·&nbsp;Vinculados: <span id="inv-progreso-vinculados"><?= $hasInvState ? ($inventario_state['summary']['ids_vinculados'] ?? 0) : 0 ?></span>
                    &nbsp;·&nbsp;Errores: <span id="inv-progreso-errores"><?= $hasInvState ? ($inventario_state['summary']['errors'] ?? 0) : 0 ?></span>
                </small>
                <strong id="inv-progreso-pct" class="text-success small"><?= $initInvPct ?>%</strong>
            </div>
            <div class="progress" style="height:8px;">
                <div id="inv-progreso-barra" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                     style="width:<?= $initInvPct ?>%"></div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <form action="<?= site_url('migraciones/sincronizar-inventario') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success"
                    <?= (!$canRun || $hasInvState) ? 'disabled' : '' ?>
                    onclick="return confirm('¿Sincronizar precios y stock de todos los productos desde Jumpseller hacia WooCommerce?');">
                    <i class="bi bi-play-fill me-1"></i>Iniciar
                </button>
            </form>
            <form action="<?= site_url('migraciones/reanudar-inventario') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-warning"
                    <?= (!$canRun || !$hasInvState) ? 'disabled' : '' ?>>
                    <i class="bi bi-play-fill me-1"></i>Continuar
                </button>
            </form>
            <form action="<?= site_url('migraciones/reiniciar-inventario') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary"
                    <?= !$hasInvState ? 'disabled' : '' ?>
                    onclick="return confirm('¿Descartar el progreso de sincronización de inventario?');">
                    <i class="bi bi-x-circle me-1"></i>Descartar progreso
                </button>
            </form>
        </div>
    </div>
</div>


<!-- ──────────────────────────────────────────────
     ACCIÓN 3 — Actualizar parámetros generales
     ────────────────────────────────────────────── -->

<!-- Barra de progreso parámetros generales -->
<div id="gen-progreso-bloque" class="card mb-2 <?= $hasGenState ? '' : 'd-none' ?>">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <small class="text-muted">
                Procesados: <strong><span id="gen-progreso-offset"><?= $hasGenState ? $generales_state['offset'] : 0 ?></span></strong>
                de <strong><span id="gen-progreso-total"><?= $hasGenState ? $generales_state['total'] : 0 ?></span></strong>
                &nbsp;·&nbsp;Actualizados: <span id="gen-progreso-updated"><?= $hasGenState ? ($generales_state['summary']['updated'] ?? 0) : 0 ?></span>
                &nbsp;·&nbsp;Omitidos: <span id="gen-progreso-skipped"><?= $hasGenState ? ($generales_state['summary']['skipped'] ?? 0) : 0 ?></span>
                &nbsp;·&nbsp;Errores: <span id="gen-progreso-errors"><?= $hasGenState ? ($generales_state['summary']['errors'] ?? 0) : 0 ?></span>
            </small>
            <strong id="gen-progreso-pct" class="text-info small"><?= $initGenPct ?>%</strong>
        </div>
        <div class="progress" style="height:8px;">
            <div id="gen-progreso-barra" class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                 style="width:<?= $initGenPct ?>%"></div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-start gap-3 mb-3">
            <div class="bg-info bg-opacity-10 rounded p-2 text-info fs-4">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">Actualizar parámetros generales</h6>
                <p class="text-muted small mb-0">
                    Carga datos desde la base de datos interna hacia WooCommerce <strong>sin tocar precios ni stock</strong>.
                    Solo se procesan productos que ya están sincronizados (con ID de WooCommerce registrado).
                    Los productos sin el dato seleccionado se omiten silenciosamente.
                    El proceso se ejecuta en páginas de 25 productos y guarda su avance si se interrumpe.
                </p>
            </div>
        </div>

        <form action="<?= site_url('woocommerce/actualizar-generales') ?>" method="post"
              onsubmit="return confirm('¿Actualizar los campos seleccionados en todos los productos sincronizados con WooCommerce?');">
            <?= csrf_field() ?>
            <div class="d-flex flex-column gap-2 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="campos[]" value="nombre" id="campo-nombre">
                    <label class="form-check-label" for="campo-nombre">
                        <strong>Nombre</strong>
                        <span class="text-muted small ms-1">— Actualiza el nombre del producto en WooCommerce con el valor del catálogo interno.</span>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="campos[]" value="marca" id="campo-marca">
                    <label class="form-check-label" for="campo-marca">
                        <strong>Marca</strong>
                        <span class="text-muted small ms-1">— Crea la marca en WooCommerce si no existe y la asigna al producto. Se omiten productos sin marca.</span>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="autocontinue" value="1" id="campo-autocontinue">
                    <label class="form-check-label" for="campo-autocontinue">
                        <strong>Continuar automáticamente</strong>
                        <span class="text-muted small ms-1">— Al terminar cada página, avanza sola sin intervención.</span>
                    </label>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-info text-white"
                    <?= (!$woocommerce_ok || $hasGenState) ? 'disabled' : '' ?>>
                    <i class="bi bi-play-fill me-1"></i>Iniciar
                </button>
            </div>
        </form>

        <div class="d-flex gap-2 mt-2">
            <form id="form-reanudar-generales" action="<?= site_url('woocommerce/reanudar-generales') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="autocontinue" value="<?= isset($_GET['autocontinue']) ? esc($_GET['autocontinue']) : '' ?>">
                <button type="submit" class="btn btn-warning"
                    <?= (!$woocommerce_ok || !$hasGenState) ? 'disabled' : '' ?>>
                    <i class="bi bi-play-fill me-1"></i>Continuar
                </button>
            </form>
            <form action="<?= site_url('woocommerce/reiniciar-generales') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary"
                    <?= !$hasGenState ? 'disabled' : '' ?>
                    onclick="return confirm('¿Descartar el progreso y empezar de cero?');">
                    <i class="bi bi-x-circle me-1"></i>Descartar progreso
                </button>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($generales_errors)): ?>
<div class="card mb-4 border-danger border-opacity-25">
    <div class="card-body p-0">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <h6 class="fw-semibold mb-0 text-danger">
                <i class="bi bi-exclamation-circle me-1"></i>Errores recientes — Parámetros generales
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Producto</th><th>WooID</th><th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($generales_errors as $err): ?>
                    <tr>
                        <td class="text-nowrap small text-muted"><?= esc($err['created_at']) ?></td>
                        <td class="small"><?= esc($err['sku']) ?></td>
                        <td class="small text-muted"><?= esc($err['nombre_producto']) ?></td>
                        <td class="small text-danger"><?= esc($err['mensaje']) ?></td>
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
const POLL_INTERVAL = 20000;

// Polling — Migración completa
<?php if ($hasState): ?>
(function () {
    const url = '<?= site_url('migraciones/progreso') ?>';
    setInterval(() => {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (!data.active) { location.reload(); return; }
                document.getElementById('progreso-bloque').classList.remove('d-none');
                document.getElementById('progreso-barra').style.width        = data.percent + '%';
                document.getElementById('progreso-pct').textContent          = data.percent + '%';
                document.getElementById('progreso-pagina').textContent       = data.last_completed_page;
                document.getElementById('progreso-total').textContent        = data.total_pages;
                document.getElementById('progreso-creados').textContent      = data.summary.created  ?? 0;
                document.getElementById('progreso-actualizados').textContent = data.summary.updated  ?? 0;
                document.getElementById('progreso-errores').textContent      = data.summary.errors   ?? 0;
            }).catch(() => {});
    }, POLL_INTERVAL);
})();
<?php endif; ?>

// Polling — Inventario
<?php if ($hasInvState): ?>
(function () {
    const url = '<?= site_url('migraciones/progreso-inventario') ?>';
    setInterval(() => {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (!data.active) { location.reload(); return; }
                document.getElementById('inv-progreso-bloque').classList.remove('d-none');
                document.getElementById('inv-progreso-barra').style.width          = data.percent + '%';
                document.getElementById('inv-progreso-pct').textContent            = data.percent + '%';
                document.getElementById('inv-progreso-pagina').textContent         = data.last_completed_page;
                document.getElementById('inv-progreso-total').textContent          = data.total_pages;
                document.getElementById('inv-progreso-actualizados').textContent   = data.summary.updated        ?? 0;
                document.getElementById('inv-progreso-vinculados').textContent     = data.summary.ids_vinculados ?? 0;
                document.getElementById('inv-progreso-errores').textContent        = data.summary.errors         ?? 0;
            }).catch(() => {});
    }, POLL_INTERVAL);
})();
<?php endif; ?>

// Polling — Parámetros generales
<?php if ($hasGenState): ?>
(function () {
    const url = '<?= site_url('woocommerce/progreso-generales') ?>';
    setInterval(() => {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (!data.active) { location.reload(); return; }
                document.getElementById('gen-progreso-bloque').classList.remove('d-none');
                document.getElementById('gen-progreso-barra').style.width   = data.percent + '%';
                document.getElementById('gen-progreso-pct').textContent     = data.percent + '%';
                document.getElementById('gen-progreso-offset').textContent  = data.offset;
                document.getElementById('gen-progreso-total').textContent   = data.total;
                document.getElementById('gen-progreso-updated').textContent = data.summary.updated  ?? 0;
                document.getElementById('gen-progreso-skipped').textContent = data.summary.skipped  ?? 0;
                document.getElementById('gen-progreso-errors').textContent  = data.summary.errors   ?? 0;
            }).catch(() => {});
    }, POLL_INTERVAL);
})();
<?php endif; ?>

// Auto-continue
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('autocontinue') === 'generales') {
        const form = document.getElementById('form-reanudar-generales');
        if (form && !form.querySelector('button[type="submit"]').disabled) {
            setTimeout(() => form.submit(), 1500);
        }
    }
})();
</script>
<?= $this->endSection() ?>
