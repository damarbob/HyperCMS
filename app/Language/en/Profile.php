<?php

// The words of the profile and settings screens that belong to accounts and sign in.
return [
    'title'               => 'Profile',
    'accountTitle'        => 'Account',
    'username'            => 'Username',
    'email'               => 'Email',
    'roles'               => 'Roles',
    'noRoles'             => 'No role',
    'passwordTitle'       => 'Password',
    'passwordHelp'        => 'Leave both boxes empty to keep your password.',
    'newPassword'         => 'New password',
    'currentPassword'     => 'Current password',
    'currentPasswordHelp' => 'Needed to change your email or your password.',
    'save'                => 'Save profile',
    'saved'               => 'Your profile is saved.',
    'usernameInvalid'     => 'A username is 3 to 30 letters, numbers, dots, dashes or underscores.',
    'usernameTaken'       => 'Someone already has that username.',
    'emailInvalid'        => 'Enter a valid email address.',
    'emailTaken'          => 'Someone already has that email.',
    'currentPasswordWrong' => 'The current password is not right.',
    'securityTitle'       => 'Sign in',
    'twoFactorOn'         => 'Two-factor is on',
    'twoFactorOff'        => 'Two-factor is off',
    'twoFactorOnText'     => 'Every sign in asks for a code that is sent to your email.',
    'twoFactorOffText'    => 'A person who manages settings can turn on a code by email at every sign in.',
    'openTokens'          => 'Access tokens',

    // The settings screen
    'settingsMine'        => 'For you',
    'settingsSite'        => 'For the site',
    'twoFactorSetting'    => 'Ask for a code by email at every sign in',
    'twoFactorSettingText' => 'The site must be able to send email, or nobody can finish signing in.',
    'twoFactorRecovery'   => 'If sign in breaks, turn it off from the server with php spark hyper:user two-factor off.',
];
