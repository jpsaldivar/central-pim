<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<form action="<?= $producto ? '/productos/update/' . $producto['id'] : '/productos/store' ?>" method="POST">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-semibold mb-3">Información del Producto</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre del Producto</label>
                            <input type="text" name="nombre" class="form-control"
                                   value="<?= esc($producto['nombre'] ?? old('nombre')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control font-monospace"
                                   value="<?= esc($producto['sku'] ?? old('sku')) ?>"
                                   placeholder="Opcional — debe ser único">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Marca</label>
                            <select name="marca_id" class="form-select">
                                <option value="">— Sin marca —</option>
                                <?php foreach ($marcas as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= (isset($producto['marca_id']) && $producto['marca_id'] == $m['id']) ? 'selected' : '' ?>>
                                    <?= esc($m['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proveedor</label>
                            <select name="proveedor_id" class="form-select">
                                <option value="">— Sin proveedor —</option>
                                <?php foreach ($proveedores as $pv): ?>
                                <option value="<?= $pv['id'] ?>" <?= (isset($producto['proveedor_id']) && $producto['proveedor_id'] == $pv['id']) ? 'selected' : '' ?>>
                                    <?= esc($pv['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-semibold mb-3">Precios y Stock</h6>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Precio Base</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio" class="form-control" step="0.01" min="0"
                                       value="<?= esc($producto['precio'] ?? old('precio', '0.00')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Precio Oferta</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio_oferta" class="form-control" step="0.01" min="0"
                                       value="<?= esc($producto['precio_oferta'] ?? old('precio_oferta')) ?>"
                                       placeholder="Opcional">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Costo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="costo" class="form-control" step="0.01" min="0"
                                       value="<?= esc($producto['costo'] ?? old('costo', '0.00')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Stock General</label>
                            <?php $esIlimitado = !empty($producto['stock_ilimitado']); ?>
                            <div class="input-group">
                                <input type="number" name="stock_general" id="stock_general_input"
                                       class="form-control" min="0"
                                       value="<?= esc($producto['stock_general'] ?? old('stock_general', 0)) ?>"
                                       <?= $esIlimitado ? 'readonly' : '' ?>>
                                <span class="input-group-text px-2" title="Stock sin límite">
                                    <div class="form-check mb-0 d-flex align-items-center gap-1">
                                        <input class="form-check-input mt-0" type="checkbox"
                                               name="stock_ilimitado" value="1"
                                               id="stock_ilimitado_check"
                                               <?= $esIlimitado ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="stock_ilimitado_check"
                                               style="font-size:1rem;line-height:1;">&#8734;</label>
                                    </div>
                                </span>
                            </div>
                            <div class="form-text">Marca &#8734; para stock sin límite (no se rastrea)</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-shop me-2 text-success"></i>Configuración por Tienda</h6>
                    <p class="text-muted small">Habilita el producto en una tienda y opcionalmente define precios/stock diferenciados. Si los dejas vacíos, se usarán los valores base.</p>
                    <?php if (empty($tiendas)): ?>
                        <div class="alert alert-info">No hay tiendas registradas. <a href="/tiendas/create">Crear tienda</a>.</div>
                    <?php else: ?>
                    <?php foreach ($tiendas as $i => $t):
                        $tc = $tiendas_config[$t['id']] ?? null;
                        $enabled = $tc !== null;
                    ?>
                    <div class="card mb-2 border">
                        <div class="card-body py-2 px-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input tienda-toggle" type="checkbox"
                                       name="tiendas[<?= $i ?>][enabled]" value="1"
                                       id="tienda_<?= $t['id'] ?>"
                                       <?= $enabled ? 'checked' : '' ?>>
                                <input type="hidden" name="tiendas[<?= $i ?>][tienda_id]" value="<?= $t['id'] ?>">
                                <label class="form-check-label fw-semibold" for="tienda_<?= $t['id'] ?>">
                                    <i class="bi bi-shop me-1"></i><?= esc($t['nombre']) ?>
                                </label>
                            </div>
                            <div class="tienda-fields <?= !$enabled ? 'd-none' : '' ?> row g-2 ms-3">
                                <div class="col-md-4">
                                    <label class="form-label small">Precio específico</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="tiendas[<?= $i ?>][valor_especifico]"
                                               class="form-control form-control-sm" step="0.01" min="0"
                                               value="<?= esc($tc['valor_especifico'] ?? '') ?>"
                                               placeholder="Usar base">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Precio oferta específico</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="tiendas[<?= $i ?>][valor_oferta_esp]"
                                               class="form-control form-control-sm" step="0.01" min="0"
                                               value="<?= esc($tc['valor_oferta_esp'] ?? '') ?>"
                                               placeholder="Opcional">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Stock específico</label>
                                    <input type="number" name="tiendas[<?= $i ?>][stock_especifico]"
                                           class="form-control form-control-sm" min="0"
                                           value="<?= esc($tc['stock_especifico'] ?? '') ?>"
                                           placeholder="Usar general">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small d-flex align-items-center gap-1">
                                        ID externo
                                        <span class="badge bg-secondary fw-normal" style="font-size:.65rem;">gestionado por el sistema</span>
                                    </label>
                                    <input type="text" name="tiendas[<?= $i ?>][external_id]"
                                           class="form-control form-control-sm font-monospace"
                                           value="<?= esc($tc['external_id'] ?? '') ?>"
                                           placeholder="Sin ID externo registrado">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-diagram-3 me-2 text-info"></i>Categorías</h6>
                    <?php if (empty($categorias)): ?>
                        <div class="text-muted small">No hay categorías. <a href="/categorias/create">Crear</a>.</div>
                    <?php else: ?>
                    <div style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($categorias as $cat): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="categorias[]" value="<?= $cat['id'] ?>"
                                   id="cat_<?= $cat['id'] ?>"
                                   <?= in_array($cat['id'], $categorias_sel) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="cat_<?= $cat['id'] ?>">
                                <?= esc($cat['nombre']) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i><?= $producto ? 'Actualizar Producto' : 'Guardar Producto' ?>
                        </button>
                        <?php if ($producto): ?>
                        <button type="button" class="btn btn-outline-primary"
                                onclick="document.getElementById('form-sync-producto').submit()">
                            <i class="bi bi-arrow-repeat me-1"></i>Sincronizar a WooCommerce
                        </button>
                        <button type="button" class="btn btn-outline-warning"
                                onclick="document.getElementById('form-sync-desde-jumpseller').submit()">
                            <i class="bi bi-cloud-download me-1"></i>Sincronizar desde Jumpseller
                        </button>
                        <?php endif; ?>
                        <a href="/productos" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php if ($producto): ?>
<form id="form-sync-producto"
      method="POST"
      action="<?= site_url('migraciones/sync-producto/' . $producto['id']) ?>"
      onsubmit="return confirm('¿Sincronizar este producto a WooCommerce?')">
    <?= csrf_field() ?>
</form>
<form id="form-sync-desde-jumpseller"
      method="POST"
      action="<?= site_url('migraciones/sync-desde-jumpseller/' . $producto['id']) ?>"
      onsubmit="return confirm('¿Actualizar este producto con los datos de Jumpseller?')">
    <?= csrf_field() ?>
</form>
<?php endif; ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.tienda-toggle').forEach(function(chk) {
    chk.addEventListener('change', function() {
        var fields = this.closest('.card-body').querySelector('.tienda-fields');
        if (this.checked) {
            fields.classList.remove('d-none');
        } else {
            fields.classList.add('d-none');
        }
    });
});

(function() {
    var chk   = document.getElementById('stock_ilimitado_check');
    var input = document.getElementById('stock_general_input');
    if (!chk || !input) return;

    function apply() {
        if (chk.checked) {
            input.value    = 0;
            input.readOnly = true;
            input.classList.add('text-muted');
        } else {
            input.readOnly = false;
            input.classList.remove('text-muted');
        }
    }
    apply();
    chk.addEventListener('change', apply);
})();
</script>
<?= $this->endSection() ?>
