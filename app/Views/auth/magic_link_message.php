<?= $this->extend('auth/layout/page') ?>

<?= $this->section('content') ?>
<div class="columns is-centered">
    <div class="column is-12-mobile is-8-tablet is-5-desktop">
        <div class="card">
            <!-- Card Header -->
            <header class="card-header">
                <p class="card-header-title">
                    <?= lang('Auth.useMagicLink') ?>
                </p>
            </header>

            <!-- Card Content -->
            <div class="card-content">
                <div class="content">
                    <p><strong><?= lang('Auth.checkYourEmail') ?></strong></p>
                    <p><?= lang('Auth.magicLinkDetails', [setting('Auth.magicLinkLifetime') / 60]) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>