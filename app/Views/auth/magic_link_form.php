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
                <!-- Notifications -->
                <?php if (session('error') !== null) : ?>
                    <div class="notification is-danger">
                        <?= session('error') ?>
                    </div>
                <?php elseif (session('errors') !== null) : ?>
                    <div class="notification is-danger">
                        <?php if (is_array(session('errors'))) : ?>
                            <?php foreach (session('errors') as $error) : ?>
                                <?= $error ?><br>
                            <?php endforeach ?>
                        <?php else : ?>
                            <?= session('errors') ?>
                        <?php endif ?>
                    </div>
                <?php endif ?>

                <!-- Magic Link Form -->
                <form action="<?= url_to('magic-link') ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- Email Field -->
                    <div class="field">
                        <label class="label" for="email"><?= lang('Auth.email') ?></label>
                        <div class="control has-icons-left">
                            <input
                                type="email"
                                class="input"
                                id="email"
                                name="email"
                                autocomplete="email"
                                placeholder="<?= lang('Auth.email') ?>"
                                value="<?= old('email', auth()->user()->email ?? null) ?>"
                                required>
                            <span class="icon is-small is-left">
                                <i class="fas fa-envelope"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="field">
                        <div class="control">
                            <button type="submit" class="button is-primary is-fullwidth">
                                <?= lang('Auth.send') ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Card Footer -->
            <div class="card-footer">
                <div class="card-footer-item">
                    <!-- Back to Login Link -->
                    <a href="<?= url_to('login') ?>"><?= lang('Auth.backToLogin') ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>