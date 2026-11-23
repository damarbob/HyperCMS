<?php

return [
    'moduleName' => 'Perbandingan Data',
    'moduleDescription' => 'Membandingkan data dari berbagai sumber dan format.',
    'moduleVersion' => '1.0.0',

    'egx' => 'contoh: {x}',

    'state' => 'State',
    'loadState' => 'Muat state',
    'deleteState' => 'Hapus state',
    'saveState' => 'Simpan state',
    'lastUsedState' => 'State terakhir yang digunakan',

    'dataSource' => 'Sumber Data',
    'dataSources' => 'Sumber-sumber Data',
    'entryx' => 'Entri {x}',
    'fieldId' => 'ID Field',
    'value' => 'Nilai',

    'addEntry' => 'Tambah entri',
    'editEntry' => 'Edit entri',
    'addField' => 'Tambah field',
    'editField' => 'Edit field',

    'failedToFetchEntries' => 'Gagal mengambil entri!',
    'failedToFetchEntryData' => 'Gagal mengambil data entri!',
    'failedToFetchStates' => 'Gagal mengambil daftar state dari server. Menggunakan daftar state yang disimpan secara lokal',
    'failedToFetchLastUsedState' => 'Gagal mengambil state terakhir yang digunakan dari server.',
    'failedToSaveLastUsedState' => 'Gagal menyimpan state terakhir yang digunakan ke server.',

    'entryFromExternalDataSource' => "Entri ini berasal dari sumber data eksternal!",
    'pleaseFinishCurrentEditFirst' => 'Harap selesaikan editan saat ini terlebih dahulu',

    'pleaseAddEntryFirst' => 'Silakan tambahkan entri terlebih dahulu.',
    'pleaseFillAllRequiredFields' => 'Harap isi semua field yang dibutuhkan.',
    'columnWithSameIdAlreadyExists' => 'Kolom dengan ID yang sama sudah ada.',
    'pleaseEnterBothFormulaLabel' => 'Harap masukkan formula dan label.',
    'pleaseSelectField' => 'Harap pilih field.',
    'pleaseProvideFormula' => 'Harap berikan formula.',
    'pleaseProvideBothFieldLabelAndId' => 'Harap berikan label dan ID field.',

    'fieldxDoesNotExist' => 'Field {x} tidak ada.',
    'fieldxAlreadyExists' => 'Field {x} sudah ada.',

    'dragToReorderEntry' => 'Geser untuk mengurutkan ulang entri',
    'dragToReorderField' => 'Geser untuk mengurutkan ulang field',

    'selectModel' => 'Pilih model',
    'chooseModel' => 'Pilih model',
    'selectAll' => 'Pilih semua',
    'selectEntry' => 'Pilih entri',
    'chooseEntry' => 'Pilih entri',

    'previewEntry' => 'Pratinjau entri',
    'contentHere' => 'Konten di sini...',

    'requiredUnlessUseExternalDataSource' => 'Diperlukan kecuali menggunakan sumber data eksternal.',
    'optionalUnlessUseExternalDataSource' => 'Opsional kecuali menggunakan sumber data eksternal.',
    'optionalLabelForDisplayPurposes' => 'Label opsional untuk tujuan tampilan.',

    'field' => 'Field',
    'fieldType' => 'Tipe field',
    'chooseFieldType' => 'Pilih tipe field',
    'customField' => 'Field kustom',
    'mathFormula' => 'Rumus matematika',
    'selectField' => 'Pilih field',
    'chooseField' => 'Pilih field',
    'fieldFormula' => 'Field rumus',
    'formulaLabel' => 'Label rumus',
    'customFieldLabel' => 'Label field kustom',
    'customFieldId' => 'ID field kustom',
    'formulaValue' => 'Rumus',
    'myCustomField' => 'Field Kustom Saya',
    'my_custom_field' => 'field_kustom_saya',

    'pleaseSelectFieldType' => "Harap pilih jenis field.",

    'selectState' => 'Pilih state',

    'enterNameForState' => 'Masukkan nama untuk state',
    'statexAlreadyExistsOverwrite' => 'State {x} sudah ada. Apakah Anda ingin menimpanya?',
    'statesSavedSuccessfully' => 'Daftar State berhasil disimpan.',
    'statexSavedSuccessfully' => 'State {x} berhasil disimpan.',
    'youHaveUnsavedChangesLoadAnyway' => 'Anda memiliki perubahan yang belum disimpan. Tetap muat?',
    'deleteStatex' => 'Hapus state {x}?',
    'statexDeletedSuccessfully' => 'State {x} berhasil dihapus.',

    'failedToSaveStates' => 'Gagal menyimpan daftar state ke server.',

    'entryUpdatedSuccessfully' => 'Entri berhasil diperbarui!',

    // Settings
    'enterDataSourcesInJsonFormat' => 'Masukkan sumber data dalam format JSON.',
    'invalidJsonFormatForStates' => 'Format JSON tidak valid untuk "states".',

    // The rebuilt screen
    'unsavedChanges' => 'Perubahan belum disimpan',
    'saveStateAs' => 'Simpan sebagai',
    'undo' => 'Urungkan',
    'redo' => 'Ulangi',
    'emptyTitle' => 'Belum ada yang dibandingkan',
    'emptyText' => 'Tambahkan entri untuk membuat kolom, dan field untuk membuat baris.',
    'removeEntry' => 'Hapus entri ini dari tabel',
    'removeField' => 'Hapus field ini dari tabel',
    'entryNumber' => 'Nomor entri',
    'entryLabel' => 'Judul kolom',
    'fieldsToAdd' => 'Field yang ditambahkan sebagai baris',
    'formulaHelp' => 'Rujuk sebuah sel dengan kurung siku. [price] adalah field price pada kolom yang sama, [e12:price] adalah field yang sama pada entri 12, dan [m3:price] pada model 3. Sel yang bukan angka dihitung 0.',
    'formulaError' => 'Galat',
    'cellCannotBeWritten' => 'Field ini tidak bisa diubah dari tabel.',
    'dataSourcesText' => 'Dari mana tabel mengambil entri. Situs ini sudah ada. Tambahkan situs lain dengan alamatnya.',
    'dataSourcesMustBeAList' => 'Sumber data harus berupa daftar dalam format JSON.',
    'dataSourceNeedsFields' => 'Setiap sumber data membutuhkan id sendiri, label, dan alamat di options.url.',
];
