<?= $this->extend('auth/layout/page') ?>

<?= $this->section('content') ?>
<!-- App Name / Title -->
<div class="block">
    <h1 class="title has-text-centered"><?= lang('Admin.appName') ?></h1>
</div>

<!-- Registration Card -->
<div class="block">
    <div class="columns is-centered">
        <div class="column is-12-mobile is-8-tablet is-5-desktop">
            <div class="card">
                <!-- Card Header -->
                <header class="card-header">
                    <p class="card-header-title">
                        <?= lang('Auth.register') ?>
                    </p>
                </header>

                <!-- Card Content -->
                <div class="card-content">
                    <!-- Error Notifications -->
                    <?php if (session('error') !== null) : ?>
                        <div class="notification is-danger"><?= session('error') ?></div>
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

                    <form action="<?= url_to('register') ?>" method="post">
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
                                    placeholder="<?= lang('Auth.email') ?>"
                                    value="<?= old('email') ?>"
                                    required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-envelope"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Username Field -->
                        <div class="field">
                            <label class="label" for="username"><?= lang('Auth.username') ?></label>
                            <div class="control has-icons-left">
                                <input
                                    type="text"
                                    class="input"
                                    id="username"
                                    name="username"
                                    placeholder="<?= lang('Auth.username') ?>"
                                    value="<?= old('username') ?>"
                                    required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="field">
                            <label class="label" for="password"><?= lang('Auth.password') ?></label>
                            <div class="control has-icons-left">
                                <input
                                    type="password"
                                    class="input"
                                    id="password"
                                    name="password"
                                    placeholder="<?= lang('Auth.password') ?>"
                                    required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Password Confirmation Field -->
                        <div class="field">
                            <label class="label" for="password_confirm"><?= lang('Auth.passwordConfirm') ?></label>
                            <div class="control has-icons-left">
                                <input
                                    type="password"
                                    class="input"
                                    id="password_confirm"
                                    name="password_confirm"
                                    placeholder="<?= lang('Auth.passwordConfirm') ?>"
                                    required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Register Button -->
                        <div class="field">
                            <div class="control">
                                <button type="submit" class="button is-primary is-fullwidth">
                                    <?= lang('Auth.register') ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Card Footer with Login Link -->
                <footer class="card-footer">
                    <div class="card-footer-item">
                        <p class="has-text-centered">
                            <?= lang('Auth.haveAccount') ?>
                            <a href="<?= url_to('login') ?>"><?= lang('Auth.login') ?></a>
                        </p>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>