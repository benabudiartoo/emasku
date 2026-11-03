<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GoldPriceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\ZakatController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect(Auth::check() ? '/ringkasan' : '/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create']);
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy']);

    Route::get('/mulai', fn () => Inertia::render('Mulai'));
    Route::get('/ringkasan', [SummaryController::class, 'index']);
    Route::get('/ringkasan/rincian/{metric}', [SummaryController::class, 'detail']);

    Route::get('/zakat', [ZakatController::class, 'index']);
    Route::get('/zakat/hitung', [ZakatController::class, 'hitung']);
    Route::get('/zakat/harga-emas', [GoldPriceController::class, 'show'])->middleware('throttle:gold-price');
    Route::post('/zakat/periode', [ZakatController::class, 'store']);
    Route::get('/zakat/periode/{period}', [ZakatController::class, 'show']);

    // Downloads: the format is the URL suffix, and anything but xlsx or pdf does not match (404).
    Route::middleware('throttle:export')->where(['format' => 'xlsx|pdf'])->group(function () {
        Route::get('/ekspor/koleksi.{format}', [ExportController::class, 'collection']);
        Route::get('/ekspor/ringkasan.{format}', [ExportController::class, 'summary']);
        Route::get('/ekspor/zakat.{format}', [ExportController::class, 'zakatList']);
        Route::get('/ekspor/zakat/{period}.{format}', [ExportController::class, 'zakatPeriod']);
    });

    Route::get('/koleksi', [ItemController::class, 'index']);
    Route::get('/koleksi/lama', [ItemController::class, 'createOld']);
    Route::get('/koleksi/baru/{kind}', [ItemController::class, 'create']);
    Route::post('/koleksi', [ItemController::class, 'store']);
    Route::get('/koleksi/{item}', [ItemController::class, 'show']);
    Route::get('/koleksi/{item}/edit', [ItemController::class, 'edit']);
    Route::patch('/koleksi/{item}', [ItemController::class, 'update']);
    Route::delete('/koleksi/{item}', [ItemController::class, 'destroy']);

    Route::get('/menu', fn () => Inertia::render('Menu'));
    Route::get('/pengaturan', [SettingsController::class, 'show']);
    Route::patch('/pengaturan', [SettingsController::class, 'update']);
    Route::get('/pengaturan/lokasi', [LocationController::class, 'index']);
    Route::post('/pengaturan/lokasi', [LocationController::class, 'store']);

    Route::patch('/pengaturan/lokasi/{location}', [LocationController::class, 'update']);
    Route::delete('/pengaturan/lokasi/{location}', [LocationController::class, 'destroy']);

    Route::post('/koleksi/{item}/lampiran', [AttachmentController::class, 'store']);
    Route::get('/lampiran/{attachment}', [AttachmentController::class, 'show']);
    Route::delete('/lampiran/{attachment}', [AttachmentController::class, 'destroy']);
});
