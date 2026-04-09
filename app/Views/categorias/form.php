<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body p-4">
                <form action="<?= $categoria ? '/categorias/update/' . $categoria['id'] : '/categorias/store' ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control"
                               value="<?= esc($categoria['nombre'] ?? old('nombre')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"><?= esc($categoria['descripcion'] ?? old('descripcion')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Categoría Padre</label>
                        <select name="parent_id" class="form-select">
                            <option value="">— Sin padre (raíz) —</option>
                            <?php foreach ($padres as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                <?= (isset($categoria['parent_id']) && $categoria['parent_id'] == $p['id']) ? 'selected' : '' ?>>
                                <?= esc($p['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i><?= $categoria ? 'Actualizar' : 'Guardar' ?>
                        </button>
                        <a href="/categorias" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
