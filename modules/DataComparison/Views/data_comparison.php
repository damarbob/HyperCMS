<?= $this->extend('admin/layout/page') ?>

<?= $this->section('head') ?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Reddit+Mono:wght@200..900&display=swap');

    #comparisonTable tr td,
    #comparisonTable tr td textarea.textarea {
        font-family: "Reddit Mono", monospace;
        font-size: calc(var(--bulma-body-font-size) * 0.9);
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="comparisonContainer"></div>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/mathjs@14.4.0/lib/browser/math.min.js"></script>
<script type="module" src="<?= module_assets_url('DataComparison', 'main.js') ?>"></script>
<?= $this->endSection() ?>