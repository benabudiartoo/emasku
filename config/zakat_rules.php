<?php

/**
 * Zakat rule sheets, one per madhhab. Rules are data so each one can be reviewed, referenced, and versioned.
 *
 * A madhhab is offered only when its sheet is complete and has references (ZakatRuleSet::for).
 * Four sheets are present: hanafi, syafii, maliki, hanbali. Adding or removing one needs no code change.
 *
 * STATUS: hanafi and syafii were drafted from online references on 2026-11-03 and reviewed by the owner
 * (task 2.2 of change `add-zakat-engine-two-madhhab`). maliki and hanbali were drafted from online references on
 * 2026-11-03 and are NOT yet reviewed by a zakat institution or scholar (task 2.1 of change
 * `add-zakat-maliki-hanbali`). Nisab figures below are all in grams of PURE gold (24 karat); the calculator
 * converts every item to pure gold first, so lower karat needs no separate nisab. The references are explanatory
 * articles and one official fatwa page, not classical texts. Treat results as a calculation aid, not a fatwa.
 *
 * `additions_join_haul` is null on purpose in every sheet: no source read states how gold added during the haul
 * is treated, so the calculator returns "needs review" instead of guessing when an addition falls inside the haul.
 * Set it to true/false only after a verified reference is added.
 *
 * Not modelled: some sources say a very small shortfall below the nisab (one or two grains) is tolerated by the
 * Maliki school and by part of the Hanbali school. The calculator compares exact weights, so it does not apply
 * that tolerance; a shortfall of any size gives "below nisab".
 *
 * Bump `version` on every change to a sheet; saved periods keep the version they were calculated with.
 */
return [
    'madhhabs' => [
        'hanafi' => [
            'label' => 'Hanafi',
            'version' => '2026-11-03.1',
            'nisab_g' => '87.48', // 20 mitsqal, converted at 4.374 g per mitsqal (NZF, CNBC Indonesia)
            'rate' => '0.025',
            // Jewelry is counted even when worn (NU Online: "kecuali menurut mazhab Hanafi"; CNBC Indonesia).
            'worn_jewelry_zakatable' => true,
            'additions_join_haul' => null,
            'references' => [
                [
                    'name' => 'NU Online, "Nishab Zakat Emas dan Perak" (17 Feb 2018)',
                    'url' => 'https://nu.or.id/syariah/nishab-zakat-emas-dan-perak-g7Rub',
                    'accessed' => '2026-11-03',
                    'note' => 'Nisab 20 dinar/mitsqal; zakat 2,5%; perhiasan halal yang dipakai tidak wajib zakat kecuali menurut mazhab Hanafi. Tabel konversi artikel ini (Hanafi 107,75 g; Syafi\'i, Maliki, Hanbali 77,50 g) TIDAK dipakai karena berbeda dari angka umum di Indonesia; lihat keputusan di proposal.',
                ],
                [
                    'name' => 'CNBC Indonesia, "Beda Zakat Emas Menurut 4 Mazhab" (21 Mar 2026)',
                    'url' => 'https://www.cnbcindonesia.com/research/20260319231453-128-720348/beda-zakat-emas-menurut-4-mazhab-wajib-untuk-perhiasan-atau-tidak',
                    'accessed' => '2026-11-03',
                    'note' => 'Hanafi: 20 mitsqal sekitar 87,48 g; emas investasi maupun perhiasan tetap dihitung; dimiliki satu tahun Hijriah. Artikel tidak menyebut rujukan kitab.',
                ],
                [
                    'name' => 'NZF, "What is the Nisab in grams?"',
                    'url' => 'https://nzf.org.uk/?p=7530',
                    'accessed' => '2026-11-03',
                    'note' => 'Nisab emas 87,48 g menurut mazhab Hanafi; mazhab lain 85 g.',
                ],
            ],
        ],

        'syafii' => [
            'label' => "Syafi'i",
            'version' => '2026-11-03.1',
            'nisab_g' => '85', // 20 dinar (20 mitsqal at 4.25 g), the figure commonly used in Indonesia
            'rate' => '0.025',
            // Permitted jewelry worn in the normal way is not zakatable; jewelry kept as savings is (NU Online, CNBC Indonesia).
            'worn_jewelry_zakatable' => false,
            'additions_join_haul' => null,
            'references' => [
                [
                    'name' => 'CNBC Indonesia, "Beda Zakat Emas Menurut 4 Mazhab" (21 Mar 2026)',
                    'url' => 'https://www.cnbcindonesia.com/research/20260319231453-128-720348/beda-zakat-emas-menurut-4-mazhab-wajib-untuk-perhiasan-atau-tidak',
                    'accessed' => '2026-11-03',
                    'note' => "Syafi'i: 20 dinar sekitar 85 g emas murni; perhiasan harian yang dipakai wajar umumnya bukan objek zakat; emas simpanan atau investasi wajib setelah mencapai nisab dan satu tahun Hijriah.",
                ],
                [
                    'name' => 'NU Online, "Nishab Zakat Emas dan Perak" (17 Feb 2018)',
                    'url' => 'https://nu.or.id/syariah/nishab-zakat-emas-dan-perak-g7Rub',
                    'accessed' => '2026-11-03',
                    'note' => 'Perhiasan halal yang dipakai wanita tidak wajib zakat kecuali menurut Hanafi; perhiasan yang melampaui batas kewajaran wajib dizakati. Tabel konversi 77,50 g artikel ini tidak dipakai (lihat keputusan di proposal).',
                ],
                [
                    'name' => 'IslamQA, "Apakah Kekurangan Sedikit dari Nisab Memengaruhi Kewajiban Zakat?"',
                    'url' => 'https://islamqa.info/id/answers/128168',
                    'accessed' => '2026-11-03',
                    'note' => "Syafi'i dan Hanbali: nisab harus tercapai sepanjang haul; bila turun di bawah nisab di tengah haul, haul terputus dan dimulai lagi.",
                ],
            ],
        ],

        'maliki' => [
            'label' => 'Maliki',
            'version' => '2026-11-03.1',
            'nisab_g' => '85', // 20 dinar, the figure commonly used in Indonesia (owner's decision; NU 77.50 g not adopted)
            'rate' => '0.025',
            // Permitted jewelry that is worn or used is not zakatable (Mufti Wilayah Persekutuan, NU Online, CNBC Indonesia).
            'worn_jewelry_zakatable' => false,
            'additions_join_haul' => null,
            'references' => [
                [
                    'name' => 'Jabatan Mufti Wilayah Persekutuan, "Irsyad al-Fatwa Siri ke-38: Zakat Perhiasan Wanita" (7 Jul 2015)',
                    'url' => 'https://muftiwp.gov.my/en/artikel/irsyad-fatwa/irsyad-fatwa-umum-cat/1980-irsyad-fatwa-series-38-zakah-for-women-s-adornments',
                    'accessed' => '2026-11-03',
                    'note' => "Maliki, Syafi'i, dan Hanbali: perhiasan yang halal, dipakai, atau digunakan tidak wajib zakat; Hanafi mewajibkan bila mencapai nisab. Perhiasan yang hanya disimpan diperlakukan seperti uang tunai. Nisab emas 85 g, zakat 2,5% dihitung tiap tahun Hijriah.",
                ],
                [
                    'name' => 'CNBC Indonesia, "Beda Zakat Emas Menurut 4 Mazhab" (21 Mar 2026)',
                    'url' => 'https://www.cnbcindonesia.com/research/20260319231453-128-720348/beda-zakat-emas-menurut-4-mazhab-wajib-untuk-perhiasan-atau-tidak',
                    'accessed' => '2026-11-03',
                    'note' => 'Maliki: nisab sekitar 85 g emas murni 24 karat; perhiasan pemakaian pribadi yang wajar dalam banyak kondisi bukan harta wajib zakat; emas simpanan yang mencapai nisab wajib setelah satu haul (satu tahun Hijriah). Artikel tidak menyebut rujukan kitab.',
                ],
                [
                    'name' => 'IslamQA, "Apakah Kekurangan Sedikit dari Nisab Memengaruhi Kewajiban Zakat?"',
                    'url' => 'https://islamqa.info/id/answers/128168',
                    'accessed' => '2026-11-03',
                    'note' => 'Harta harus dimiliki satu haul penuh tanpa terputus; bila turun di bawah nisab di tengah haul, haul terputus. Maliki menoleransi kekurangan sangat kecil (satu atau dua biji); toleransi ini tidak diterapkan di aplikasi.',
                ],
                [
                    'name' => 'NU Online, "Nishab Zakat Emas dan Perak" (17 Feb 2018)',
                    'url' => 'https://nu.or.id/syariah/nishab-zakat-emas-dan-perak-g7Rub',
                    'accessed' => '2026-11-03',
                    'note' => 'Perhiasan halal yang dipakai tidak wajib zakat kecuali menurut Hanafi. Tabel konversi 77,50 g artikel ini tidak dipakai (lihat keputusan di proposal).',
                ],
            ],
        ],

        'hanbali' => [
            'label' => 'Hanbali',
            'version' => '2026-11-03.1',
            'nisab_g' => '85', // 20 dinar, the figure commonly used in Indonesia (owner's decision; NU 77.50 g not adopted)
            'rate' => '0.025',
            // Jewelry intended for ordinary wear is not zakatable (Mufti Wilayah Persekutuan, NU Online, CNBC Indonesia).
            'worn_jewelry_zakatable' => false,
            'additions_join_haul' => null,
            'references' => [
                [
                    'name' => 'Jabatan Mufti Wilayah Persekutuan, "Irsyad al-Fatwa Siri ke-38: Zakat Perhiasan Wanita" (7 Jul 2015)',
                    'url' => 'https://muftiwp.gov.my/en/artikel/irsyad-fatwa/irsyad-fatwa-umum-cat/1980-irsyad-fatwa-series-38-zakah-for-women-s-adornments',
                    'accessed' => '2026-11-03',
                    'note' => "Maliki, Syafi'i, dan Hanbali: perhiasan yang halal, dipakai, atau digunakan tidak wajib zakat; Hanafi mewajibkan bila mencapai nisab. Perhiasan yang hanya disimpan diperlakukan seperti uang tunai. Nisab emas 85 g, zakat 2,5% dihitung tiap tahun Hijriah.",
                ],
                [
                    'name' => 'CNBC Indonesia, "Beda Zakat Emas Menurut 4 Mazhab" (21 Mar 2026)',
                    'url' => 'https://www.cnbcindonesia.com/research/20260319231453-128-720348/beda-zakat-emas-menurut-4-mazhab-wajib-untuk-perhiasan-atau-tidak',
                    'accessed' => '2026-11-03',
                    'note' => 'Hanbali: zakat wajib bila emas mencapai atau melebihi 85 g emas murni 24 karat dan dimiliki satu haul; perhiasan untuk pemakaian biasa tidak dikenai zakat. Artikel tidak menyebut rujukan kitab.',
                ],
                [
                    'name' => 'IslamQA, "Apakah Kekurangan Sedikit dari Nisab Memengaruhi Kewajiban Zakat?"',
                    'url' => 'https://islamqa.info/id/answers/128168',
                    'accessed' => '2026-11-03',
                    'note' => 'Harta harus dimiliki satu haul penuh tanpa terputus; bila turun di bawah nisab di tengah haul, haul terputus. Hanbali: Al-Khiraqi menyatakan kekurangan sedikit berarti tidak wajib; sebagian ulama Hanbali lain tetap mewajibkan. Aplikasi membandingkan angka tepat, jadi tidak ada toleransi.',
                ],
                [
                    'name' => 'NU Online, "Nishab Zakat Emas dan Perak" (17 Feb 2018)',
                    'url' => 'https://nu.or.id/syariah/nishab-zakat-emas-dan-perak-g7Rub',
                    'accessed' => '2026-11-03',
                    'note' => 'Perhiasan halal yang dipakai tidak wajib zakat kecuali menurut Hanafi. Tabel konversi 77,50 g artikel ini tidak dipakai (lihat keputusan di proposal).',
                ],
            ],
        ],
    ],
];
