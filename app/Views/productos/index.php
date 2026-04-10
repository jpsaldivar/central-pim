<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">Catálogo de Productos</h6>
            <a href="/productos/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nuevo Producto
            </a>
        </div>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Marca</th>
                    <th>Proveedor</th>
                    <th>Precio</th>
                    <th>Costo</th>
                    <th>Stock</th>
                    <?php foreach ($tiendas as $t): ?>
                    <th class="text-center"><?= esc($t['nombre']) ?></th>
                    <?php endforeach; ?>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($productos)): ?>
                <tr><td colspan="<?= 8 + count($tiendas) ?>" class="text-center text-muted py-4">No hay productos registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($productos as $p): ?>
                <tr>
                    <td class="text-muted"><?= $p['id'] ?></td>
                    <td><?= esc($p['nombre']) ?></td>
                    <td><?= esc($p['marca_nombre'] ?? '—') ?></td>
                    <td><?= esc($p['proveedor_nombre'] ?? '—') ?></td>
                    <td>$<?= number_format($p['precio'], 2) ?>
                        <?php if ($p['precio_oferta']): ?>
                            <br><small class="text-danger">Oferta: $<?= number_format($p['precio_oferta'], 2) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>$<?= number_format($p['costo'], 2) ?></td>
                    <td>
                        <span class="badge <?= $p['stock_general'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                            <?= $p['stock_general'] ?>
                        </span>
                    </td>
                    <?php foreach ($tiendas as $t): ?>
                    <td class="text-center">
                        <?php
                        if (!isset($producto_tiendas[$p['id']][$t['id']])) {
                            // No está en la tienda
                            echo '<i class="bi bi-x-circle-fill text-danger" title="No disponible en ' . esc($t['nombre']) . '"></i>';
                        } else {
                            $pt = $producto_tiendas[$p['id']][$t['id']];
                            $stock = $pt['stock_especifico'] !== null ? (int)$pt['stock_especifico'] : (int)$p['stock_general'];
                            if ($stock > 0) {
                                echo '<i class="bi bi-check-circle-fill text-success" title="Disponible con stock en ' . esc($t['nombre']) . '"></i>';
                            } else {
                                echo '<i class="bi bi-check-circle-fill text-warning" title="Disponible sin stock en ' . esc($t['nombre']) . '"></i>';
                            }
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="text-end">
                        <a href="/productos/edit/<?= $p['id'] ?>" class="btn btn-outline-secondary btn-action me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/productos/delete/<?= $p['id'] ?>" class="btn btn-outline-danger btn-action"
                           onclick="return confirm('¿Eliminar este producto?')">
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
