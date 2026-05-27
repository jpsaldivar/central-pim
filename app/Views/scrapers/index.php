<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">Tiendas de monitoreo</h6>
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Cada ejecución guarda un snapshot de precios. Los precios históricos se conservan.
            </small>
        </div>

        <?php if (empty($tiendas)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-shop fs-2 d-block mb-2"></i>
                No hay tiendas scraper configuradas.<br>
                <small>Ejecuta el seed <code>ScraperTiendasSeeder</code> o dálas de alta en Tiendas.</small>
            </div>
        <?php else: ?>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Tienda</th>
                    <th>Plataforma</th>
                    <th>Última ejecución</th>
                    <th>Estado</th>
                    <th class="text-end">Encontrados</th>
                    <th class="text-end">Nuevos</th>
                    <th class="text-end">Cambios</th>
                    <th class="text-end">Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tiendas as $t): ?>
                <?php
                    $estado       = $t['estado'] ?? null;
                    $badgeClass   = match($estado) {
                        'completado'  => 'bg-success',
                        'en_progreso' => 'bg-warning text-dark',
                        'error'       => 'bg-danger',
                        default       => 'bg-secondary',
                    };
                    $badgeLabel   = match($estado) {
                        'completado'  => 'Completado',
                        'en_progreso' => 'En progreso',
                        'error'       => 'Error',
                        default       => 'Sin ejecutar',
                    };
                    $canRun = $estado !== 'en_progreso';
                ?>
                <tr>
                    <td class="fw-semibold">
                        <?= esc($t['tienda_nombre']) ?>
                        <?php if ($t['run_id'] && $estado === 'error'): ?>
                            <button type="button"
                                    class="btn btn-link p-0 ms-1 btn-ver-error"
                                    data-mensaje="<?= esc($t['mensaje_error'] ?? 'Sin detalle de error.') ?>"
                                    title="Ver detalle del error">
                                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code class="small"><?= esc($t['plataforma']) ?></code>
                    </td>
                    <td class="text-muted small">
                        <?php if ($t['iniciado_en']): ?>
                            <?= esc(date('d/m/Y H:i', strtotime($t['iniciado_en']))) ?>
                            <?php if ($t['finalizado_en']): ?>
                                <span class="text-muted">
                                    (<?= esc(gmdate('i\m s\s', strtotime($t['finalizado_en']) - strtotime($t['iniciado_en']))) ?>)
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                    </td>
                    <td class="text-end"><?= $t['total_encontrados'] ?? '—' ?></td>
                    <td class="text-end">
                        <?php if ($t['total_nuevos'] !== null): ?>
                            <span class="<?= $t['total_nuevos'] > 0 ? 'text-success fw-semibold' : 'text-muted' ?>">
                                <?= $t['total_nuevos'] ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($t['total_cambios'] !== null): ?>
                            <span class="<?= $t['total_cambios'] > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>">
                                <?= $t['total_cambios'] ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <?php if ($t['run_id']): ?>
                                <a href="<?= site_url('scrapers/show/' . $t['run_id']) ?>"
                                   class="btn btn-outline-secondary btn-action"
                                   title="Ver último run">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php endif; ?>
                            <form method="POST" action="<?= site_url('scrapers/run/' . $t['tienda_id']) ?>"
                                  onsubmit="return confirm('¿Iniciar scraping de <?= esc($t['tienda_nombre']) ?>?')">
                                <?= csrf_field() ?>
                                <button type="submit"
                                        class="btn btn-primary btn-action"
                                        <?= !$canRun ? 'disabled title="Ya hay un run en progreso"' : '' ?>>
                                    <i class="bi bi-play-fill me-1"></i>Ejecutar
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: detalle de error -->
<div class="modal fade" id="modalError" tabindex="-1" aria-labelledby="modalErrorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-semibold" id="modalErrorLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Detalle del error
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="modal-error-msg" class="bg-light rounded p-3 small mb-0"
                     style="white-space: pre-wrap; word-break: break-word;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const modalError    = new bootstrap.Modal(document.getElementById('modalError'));
const modalErrorMsg = document.getElementById('modal-error-msg');

document.querySelectorAll('.btn-ver-error').forEach(btn => {
    btn.addEventListener('click', () => {
        modalErrorMsg.textContent = btn.dataset.mensaje;
        modalError.show();
    });
});
</script>
<?= $this->endSection() ?>
