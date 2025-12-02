<?php

use Illuminate\Support\Facades\Route;
use App\Contracts\AstronomyClientInterface;
use App\Services\AstronomyApiService;
use App\Services\CachedAstronomyClient;
app()->bind(AstronomyClientInterface::class, function () {
    $service = new AstronomyApiService(
        env('ASTRO_APP_ID'),
        env('ASTRO_APP_SECRET')
    );

    return new CachedAstronomyClient($service);
});
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssController;
use App\Http\Controllers\OsdrController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\AstroController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\UploadController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Страница МКС
Route::get('/iss', [IssController::class, 'index'])->name('iss');

// Прокси-маршруты для JS на фронтенде (обращаются к go_iss через PHP)
Route::get('/last', [ProxyController::class, 'last']);
Route::get('/iss/trend', [ProxyController::class, 'trend']);

// Страница OSDR (данные NASA)
Route::get('/osdr', [OsdrController::class, 'index'])->name('osdr');

// Астрономические события (AJAX запрос)
Route::get('/astro/events', [AstroController::class, 'events'])->name('astro.events');

// CMS страницы (например, /cms/welcome)
Route::get('/cms/{slug}', [CmsController::class, 'page'])->name('cms.page');

// JWST галерея (JSON)
Route::get('/jwst/feed', [DashboardController::class, 'jwstFeed']);

// Загрузка файлов
Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');