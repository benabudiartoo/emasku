<?php

/**
 * Zakat rule sheets, one per madhhab. Rules are data so each one can be reviewed, referenced, and versioned.
 *
 * A madhhab is offered only when its sheet is complete and has references (ZakatRuleSet::for).
 * Maliki and Hanbali are not here yet (a later increment).
 *
 * STATUS: drafted from online references on 2026-11-03 and NOT yet reviewed by a zakat institution or scholar
 * (task 2.2 of change `add-zakat-engine-two-madhhab`). Nisab figures below are all in grams of PURE gold
 * (24 karat); the calculator converts every item to pure gold first, so lower karat needs no separate nisab.
 * The references are explanatory articles, not classical texts. Treat results as a calculation aid, not a fatwa.
 *
 * `additions_join_haul` is null on purpose: no source read for this sheet states how gold added during the haul
 * is treated, so the calculator returns "needs review" instead of guessing when an addition falls inside the haul.
 * Set it to true/false only after a verified reference is added.
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
    ],
];
