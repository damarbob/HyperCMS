<?= $this->extend('auth/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <h1 class="title has-text-centered"><?= lang('Admin.appName') ?></h1>
</div>
<div class="block">
    <!-- Use columns to center the card -->
    <div class="columns is-centered">
        <div class="column is-12-mobile is-8-tablet is-5-desktop">
            <div class="card">
                <!-- Card header for the title -->
                <header class="card-header">
                    <p class="card-header-title">
                        <?= lang('Auth.login') ?>
                    </p>
                </header>

                <!-- Card content with the form and notifications -->
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

                    <!-- Success Notification -->
                    <?php if (session('message') !== null) : ?>
                        <div class="notification is-success"><?= session('message') ?></div>
                    <?php endif ?>

                    <!-- Login Form -->
                    <form action="<?= url_to('login') ?>" method="post">
                        <?= csrf_field() ?>

                        <!-- Email Field -->
                        <div class="field">
                            <label class="label" for="email"><?= lang('Auth.email') ?></label>
                            <div class="control has-icons-left">
                                <input
                                    class="input"
                                    type="email"
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

                        <!-- Password Field -->
                        <div class="field">
                            <label class="label" for="password"><?= lang('Auth.password') ?></label>
                            <div class="control has-icons-left">
                                <input
                                    class="input"
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="<?= lang('Auth.password') ?>"
                                    required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Remember Me Checkbox -->
                        <?php if (setting('Auth.sessionConfig')['allowRemembering']) : ?>
                            <div class="field">
                                <div class="control">
                                    <label class="checkbox">
                                        <input type="checkbox" name="remember" <?php if (old('remember')): ?> checked<?php endif ?>>
                                        <?= lang('Auth.rememberMe') ?>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Submit Button -->
                        <div class="field">
                            <div class="control">
                                <button type="submit" class="button is-primary is-fullwidth">
                                    <?= lang('Auth.login') ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Card Footer with links -->
                <footer class="card-footer">
                    <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
                        <div class="card-footer-item">
                            <div class="block">
                                <div>
                                    <a href="<?= url_to('magic-link') ?>"><?= lang('Auth.forgotPassword') ?></a>
                                </div>
                            </div>
                        </div>
                    <?php endif ?>

                    <?php if (setting('Auth.allowRegistration')) : ?>
                        <div class="card-footer-item">
                            <div class="block">
                                <div>
                                    <a href="<?= url_to('register') ?>"><?= lang('Auth.needAccount') ?></a>
                                </div>
                            </div>
                        </div>
                    <?php endif ?>
                </footer>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>