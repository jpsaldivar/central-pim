<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-4">
    <!-- Formulario para agregar/editar mapeo -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header py-3 fw-semibold">Agregar mapeo</div>
            <div class="card-body">
                <form method="POST" action="/bsale/variant-map" id="form-mapeo">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">SKU (WooCommerce)</label>
                        <div class="input-group">
                            <input type="text" name="sku" id="input-sku" class="form-control"
                                   placeholder="ej: CAM-NKD3500" required>
                            <button type="button" class="btn btn-outline-secondary" id="btn-buscar">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div id="sku-resultados" class="list-group mt-1 d-none"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">woo_product_id</label>
                        <input type="number" name="woo_product_id" id="input-woo-id" class="form-control"
                               placeholder="ID del producto en WooCommerce" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">bsale_variant_id</label>
                        <input type="number" name="bsale_variant_id" id="input-bsale-id" class="form-control"
                               placeholder="ID de la variante en Bsale" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre en Bsale</label>
                        <input type="text" name="bsale_product_name" id="input-nombre" class="form-control"
                               placeholder="Nombre del producto (opcional)">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i>Guardar mapeo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabla de mapeos existentes -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-3 fw-semibold">Mapeos registrados (<?= count($mapeos) ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($mapeos)): ?>
                    <p class="text-muted text-center py-4 mb-0">Sin mapeos registrados. Agrega el primero.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>woo_product_id</th>
                                <th>bsale_variant_id</th>
                                <th>Nombre en Bsale</th>
                                <th>Activo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mapeos as $m): ?>
                            <tr>
                                <td><code><?= esc($m['sku']) ?></code></td>
                                <td><?= esc($m['woo_product_id']) ?></td>
                                <td><?= esc($m['bsale_variant_id']) ?></td>
                                <td class="text-muted small"><?= esc($m['bsale_product_name'] ?? '—') ?></td>
                                <td>
                                    <?php if ($m['activo']): ?>
                                        <span class="badge bg-success">sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">no</span>
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
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('btn-buscar').addEventListener('click', function () {
    const sku       = document.getElementById('input-sku').value.trim();
    const container = document.getElementById('sku-resultados');

    if (!sku) return;

    container.innerHTML = '<div class="list-group-item text-muted small">Buscando...</div>';
    container.classList.remove('d-none');

    fetch('/bsale/search-variant?sku=' + encodeURIComponent(sku))
        .then(r => r.json())
        .then(variants => {
            container.innerHTML = '';
            if (!variants.length) {
                container.innerHTML = '<div class="list-group-item text-muted small">Sin resultados en Bsale.</div>';
                return;
            }
            variants.forEach(v => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action small';
                btn.textContent = `#${v.id} — ${v.sku} — ${v.product_name}`;
                btn.addEventListener('click', () => {
                    document.getElementById('input-bsale-id').value = v.id;
                    document.getElementById('input-nombre').value   = v.product_name;
                    container.classList.add('d-none');
                });
                container.appendChild(btn);
            });
        })
        .catch(() => {
            container.innerHTML = '<div class="list-group-item text-danger small">Error al consultar Bsale.</div>';
        });
});
</script>
<?= $this->endSection() ?>
