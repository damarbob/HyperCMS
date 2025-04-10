<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <h1 class="title">
        <?= lang('Admin.welcomex', ['x' => auth()->user()->username]) ?>
    </h1>
    <p class="subtitle is-hidden">
        This is the <strong>Dashboard</strong>!
    </p>
</div>
<?php if ($models) : ?>
    <div class="block">
        <div class="fixed-grid has-4-cols">
            <div class="grid">
                <?php foreach ($models as $model) : ?>
                    <div class="cell">
                        <a class="title" href="<?= base_url('admin/model?id=' . $model['id']) ?>">
                            <span class="icon is-large">
                                <i class=" fa-solid fa-box-open"></i>
                            </span>
                            <span class="text"><?= $model['name'] ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>