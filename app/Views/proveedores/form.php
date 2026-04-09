<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body p-4">
                <form action="<?= $proveedor ? '/proveedores/update/' . $proveedor['id'] : '/proveedores/store' ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre / Razón Social</label>
                        <input type="text" name="nombre" class="form-control"
                               value="<?= esc($proveedor['nombre'] ?? old('nombre')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tiempo de Encargo (días)</label>
                        <input type="number" name="tiempo_encargo" class="form-control" min="0"
                               value="<?= esc($proveedor['tiempo_encargo'] ?? old('tiempo_encargo', 0)) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contacto</label>
                        <input type="text" name="contacto" class="form-control"
                               value="<?= esc($proveedor['contacto'] ?? old('contacto')) ?>">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i><?= $proveedor ? 'Actualizar' : 'Guardar' ?>
                        </button>
                        <a href="/proveedores" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
