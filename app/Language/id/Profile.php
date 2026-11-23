<?php

// Kata-kata layar profil dan pengaturan yang berkaitan dengan akun dan masuk.
return [
    'title'               => 'Profil',
    'accountTitle'        => 'Akun',
    'username'            => 'Nama pengguna',
    'email'               => 'Email',
    'roles'               => 'Peran',
    'noRoles'             => 'Tanpa peran',
    'passwordTitle'       => 'Kata sandi',
    'passwordHelp'        => 'Kosongkan kedua kotak untuk mempertahankan kata sandi Anda.',
    'newPassword'         => 'Kata sandi baru',
    'currentPassword'     => 'Kata sandi saat ini',
    'currentPasswordHelp' => 'Diperlukan untuk mengganti email atau kata sandi Anda.',
    'save'                => 'Simpan profil',
    'saved'               => 'Profil Anda sudah disimpan.',
    'usernameInvalid'     => 'Nama pengguna terdiri dari 3 sampai 30 huruf, angka, titik, tanda hubung, atau garis bawah.',
    'usernameTaken'       => 'Sudah ada yang memakai nama pengguna itu.',
    'emailInvalid'        => 'Masukkan alamat email yang valid.',
    'emailTaken'          => 'Sudah ada yang memakai email itu.',
    'currentPasswordWrong' => 'Kata sandi saat ini tidak benar.',
    'securityTitle'       => 'Masuk',
    'twoFactorOn'         => 'Verifikasi dua langkah aktif',
    'twoFactorOff'        => 'Verifikasi dua langkah mati',
    'twoFactorOnText'     => 'Setiap kali masuk diminta kode yang dikirim ke email Anda.',
    'twoFactorOffText'    => 'Orang yang mengelola pengaturan bisa mengaktifkan kode lewat email di setiap proses masuk.',
    'openTokens'          => 'Token akses',

    // Layar pengaturan
    'settingsMine'        => 'Untuk Anda',
    'settingsSite'        => 'Untuk situs',
    'twoFactorSetting'    => 'Minta kode lewat email di setiap proses masuk',
    'twoFactorSettingText' => 'Situs harus bisa mengirim email, kalau tidak, tidak ada yang bisa menyelesaikan proses masuk.',
    'twoFactorRecovery'   => 'Jika proses masuk bermasalah, matikan dari server dengan php spark hyper:user two-factor off.',
];
