<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body p-4">
                <form action="<?= $marca ? '/marcas/update/' . $marca['id'] : '/marcas/store' ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la Marca</label>
                        <input type="text" name="nombre" class="form-control"
                               value="<?= esc($marca['nombre'] ?? old('nombre')) ?>" required autofocus>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i><?= $marca ? 'Actualizar' : 'Guardar' ?>
                        </button>
                        <a href="/marcas" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
