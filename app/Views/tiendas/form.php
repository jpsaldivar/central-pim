<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-body p-4">
                <form action="<?= $tienda ? '/tiendas/update/' . $tienda['id'] : '/tiendas/store' ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la Tienda</label>
                        <input type="text" name="nombre" class="form-control"
                               value="<?= esc($tienda['nombre'] ?? old('nombre')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL del API (endpoint de sincronización)</label>
                        <input type="url" name="url_api" class="form-control"
                               value="<?= esc($tienda['url_api'] ?? old('url_api')) ?>"
                               placeholder="https://mitienda.com/api/sync" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Token de Autenticación</label>
                        <input type="text" name="token_auth" class="form-control"
                               value="<?= esc($tienda['token_auth'] ?? old('token_auth')) ?>" required>
                        <div class="form-text">Token secreto para validar las notificaciones.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i><?= $tienda ? 'Actualizar' : 'Guardar' ?>
                        </button>
                        <a href="/tiendas" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
