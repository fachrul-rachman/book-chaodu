<?php

use App\Http\Controllers\Admin\BookingApprovalController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingIntegrationRetryController;
use App\Http\Controllers\Admin\BookingQrFileController;
use App\Http\Controllers\Admin\BookingRejectionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PaymentProofFileController;
use App\Http\Controllers\Admin\PrayerPaperFileController;
use App\Http\Controllers\Admin\PrayerPaperMarkingController;
use App\Http\Controllers\Admin\PrayerPaperMarkingImageController;
use App\Http\Controllers\Admin\PrayerPaperPreviewController;
use App\Http\Controllers\Admin\PrayerPaperPreviewDownloadController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TableLayoutController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Checker\CheckInController;
use App\Http\Controllers\Checker\DashboardController as CheckerDashboardController;
use App\Http\Controllers\PackageImageController;
use App\Http\Controllers\PrayerPaperTemplateImageController;
use App\Http\Controllers\PublicBookingPageController;
use App\Http\Controllers\PublicBookingSuccessController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicBookingPageController::class)->name('home');
Route::get('/media/kertas-doa/{type}', PrayerPaperTemplateImageController::class)
    ->whereIn('type', ['A', 'B'])
    ->name('public.prayer-paper-template.image.show');
Route::get('/booking/berhasil/{bookingNumber}', PublicBookingSuccessController::class)
    ->name('public.booking.success');

Route::redirect('/login', '/masuk');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/masuk', [AuthenticatedSessionController::class, 'store'])
        ->name('authenticate');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/admin', DashboardController::class)
            ->name('admin.dashboard');
        Route::get('/admin/paket', [PackageController::class, 'index'])
            ->name('admin.packages.index');
        Route::post('/admin/paket/{package}', [PackageController::class, 'update'])
            ->name('admin.packages.update');
        Route::get('/admin/booking', [BookingController::class, 'index'])
            ->name('admin.bookings.index');
        Route::get('/admin/booking/{booking}', [BookingController::class, 'show'])
            ->name('admin.bookings.show');
        Route::put('/admin/booking/{booking}', [BookingController::class, 'update'])
            ->name('admin.bookings.update');
        Route::post('/admin/booking/{booking}/setuju', BookingApprovalController::class)
            ->name('admin.bookings.approve');
        Route::post('/admin/booking/{booking}/tolak', BookingRejectionController::class)
            ->name('admin.bookings.reject');
        Route::post('/admin/booking/{booking}/integrasi/{component}/retry', BookingIntegrationRetryController::class)
            ->whereIn('component', ['qr', 'drive', 'notion', 'approval_email'])
            ->name('admin.bookings.integrations.retry');
        Route::get('/admin/booking/{booking}/bukti', PaymentProofFileController::class)
            ->name('admin.bookings.proof.show');
        Route::get('/admin/booking/{booking}/qr', BookingQrFileController::class)
            ->name('admin.bookings.qr.show');
        Route::get('/admin/pembayaran', [SettingController::class, 'edit'])
            ->name('admin.settings.edit');
        Route::put('/admin/pembayaran', [SettingController::class, 'update'])
            ->name('admin.settings.update');
        Route::get('/admin/layout-meja', TableLayoutController::class)
            ->name('admin.table-layout');
        Route::get('/admin/laporan', ReportController::class)
            ->name('admin.reports.index');
        Route::get('/admin/laporan/export/{tab}/{format}', ReportExportController::class)
            ->name('admin.reports.export');
        Route::get('/admin/kertas-doa/marking', [PrayerPaperMarkingController::class, 'edit'])
            ->name('admin.prayer-paper-marking.edit');
        Route::put('/admin/kertas-doa/marking', [PrayerPaperMarkingController::class, 'update'])
            ->name('admin.prayer-paper-marking.update');
        Route::get('/admin/kertas-doa/marking/gambar', PrayerPaperMarkingImageController::class)
            ->name('admin.prayer-paper-marking.image.show');
        Route::get('/admin/kertas-doa/cek-cepat', PrayerPaperPreviewController::class)
            ->name('admin.prayer-paper-preview');
        Route::get('/admin/kertas-doa/cek-cepat/download', PrayerPaperPreviewDownloadController::class)
            ->name('admin.prayer-paper-preview.download');
        Route::get('/admin/kertas-doa/{prayerPaper}', PrayerPaperFileController::class)
            ->name('admin.prayer-papers.show');
    });

    Route::middleware('role:CHECKER')->group(function () {
        Route::get('/checker', CheckerDashboardController::class)
            ->name('checker.dashboard');
        Route::post('/checker/check-in/{booking}', CheckInController::class)
            ->name('checker.check-in');
    });
});

Route::get('/media/paket/{package}', [PackageImageController::class, 'show'])
    ->name('packages.image.show');
