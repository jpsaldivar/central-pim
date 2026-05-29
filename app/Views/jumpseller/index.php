<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card">
    <div class="card-body p-5 text-center">
        <div class="text-muted mb-3" style="font-size:3rem;">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <h5 class="fw-semibold mb-2">Próximamente</h5>
        <p class="text-muted mb-0">
            La integración directa con Jumpseller está en desarrollo.<br>
            Por ahora, las operaciones que involucran Jumpseller se gestionan desde la sección
            <a href="<?= site_url('woocommerce') ?>">WooCommerce</a>.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
