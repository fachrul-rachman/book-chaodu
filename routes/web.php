<?php

use App\Http\Controllers\Admin\BookingApprovalController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingIntegrationRetryController;
use App\Http\Controllers\Admin\BookingQrFileController;
use App\Http\Controllers\Admin\BookingRejectionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GallerySettingController;
use App\Http\Controllers\Admin\GalleryWallpaperController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PaymentProofFileController;
use App\Http\Controllers\Admin\PrayerPaperFileController;
use App\Http\Controllers\Admin\PrayerPaperMarkingController;
use App\Http\Controllers\Admin\PrayerPaperMarkingImageController;
use App\Http\Controllers\Admin\PrayerPaperPreviewController;
use App\Http\Controllers\Admin\PrayerPaperPreviewDownloadController;
use App\Http\Controllers\Admin\PrayerPaperTextSettingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TableLayoutController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Checker\CheckInController;
use App\Http\Controllers\Checker\DashboardController as CheckerDashboardController;
use App\Http\Controllers\Content\CustomerMediaController;
use App\Http\Controllers\Content\CustomerMediaOrderController;
use App\Http\Controllers\Content\CustomerMediaUploadController;
use App\Http\Controllers\Content\DashboardController as ContentDashboardController;
use App\Http\Controllers\Content\GlobalMediaController;
use App\Http\Controllers\Content\GlobalMediaOrderController;
use App\Http\Controllers\Content\GlobalMediaUploadController;
use App\Http\Controllers\PackageImageController;
use App\Http\Controllers\PrayerPaperPreviewImageController;
use App\Http\Controllers\PrayerPaperTemplateImageController;
use App\Http\Controllers\Printer\BookingPrintedController;
use App\Http\Controllers\Printer\DashboardController as PrinterDashboardController;
use App\Http\Controllers\Printer\PrayerPaperFileController as PrinterPrayerPaperFileController;
use App\Http\Controllers\PublicBookingPageController;
use App\Http\Controllers\PublicBookingPaymentPageController;
use App\Http\Controllers\PublicBookingSuccessController;
use App\Http\Controllers\PublicGalleryAlbumController;
use App\Http\Controllers\PublicGalleryArchiveController;
use App\Http\Controllers\PublicGalleryArchiveDownloadController;
use App\Http\Controllers\PublicGalleryMediaController;
use App\Http\Controllers\PublicGalleryMediaDownloadController;
use App\Http\Controllers\PublicGalleryViewerMediaController;
use App\Http\Controllers\PublicGalleryWallpaperController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicBookingPageController::class)->name('home');
Route::get('/media/kertas-doa/{type}', PrayerPaperTemplateImageController::class)
    ->whereIn('type', ['A', 'B'])
    ->name('public.prayer-paper-template.image.show');
Route::get('/preview/kertas/render', PrayerPaperPreviewImageController::class)
    ->name('prayer-paper-preview.image');
Route::get('/booking/berhasil/{bookingNumber}', PublicBookingSuccessController::class)
    ->name('public.booking.success');
Route::get('/booking/pembayaran/{bookingNumber}', PublicBookingPaymentPageController::class)
    ->name('public.booking.payment.show');
Route::middleware('gallery.private')->group(function (): void {
    Route::get('/chaodu/{bookingNumber}', PublicGalleryAlbumController::class)
        ->middleware('throttle:public-gallery-album')
        ->name('public.gallery.show');
    Route::get('/chaodu/{bookingNumber}/wallpaper', PublicGalleryWallpaperController::class)
        ->middleware('throttle:public-gallery-media')
        ->name('public.gallery.wallpaper');
    Route::get('/chaodu/{bookingNumber}/media/{media}', PublicGalleryMediaController::class)
        ->whereNumber('media')
        ->middleware('throttle:public-gallery-media')
        ->name('public.gallery.media.preview');
    Route::get('/chaodu/{bookingNumber}/media/{media}/viewer', PublicGalleryViewerMediaController::class)
        ->whereNumber('media')
        ->middleware('throttle:public-gallery-media')
        ->name('public.gallery.media.viewer');
    Route::get('/chaodu/{bookingNumber}/media/{media}/download', PublicGalleryMediaDownloadController::class)
        ->whereNumber('media')
        ->middleware('throttle:public-gallery-media')
        ->name('public.gallery.media.download');
    Route::get('/chaodu/{bookingNumber}/archive', [PublicGalleryArchiveController::class, 'show'])
        ->middleware('throttle:public-gallery-archive')
        ->name('public.gallery.archive.show');
    Route::post('/chaodu/{bookingNumber}/archive', [PublicGalleryArchiveController::class, 'store'])
        ->middleware('throttle:public-gallery-archive')
        ->name('public.gallery.archive.store');
    Route::get('/chaodu/{bookingNumber}/archive/download', PublicGalleryArchiveDownloadController::class)
        ->middleware('throttle:public-gallery-archive')
        ->name('public.gallery.archive.download');
});

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
            ->whereIn('component', ['qr', 'approval_email'])
            ->name('admin.bookings.integrations.retry');
        Route::get('/admin/booking/{booking}/bukti', PaymentProofFileController::class)
            ->name('admin.bookings.proof.show');
        Route::get('/admin/booking/{booking}/qr', BookingQrFileController::class)
            ->name('admin.bookings.qr.show');
        Route::get('/admin/pembayaran', [SettingController::class, 'edit'])
            ->name('admin.settings.edit');
        Route::put('/admin/pembayaran', [SettingController::class, 'update'])
            ->name('admin.settings.update');
        Route::get('/admin/galeri', [GallerySettingController::class, 'edit'])
            ->name('admin.gallery-settings.edit');
        Route::post('/admin/galeri', [GallerySettingController::class, 'update'])
            ->name('admin.gallery-settings.update');
        Route::get('/admin/galeri/wallpaper', GalleryWallpaperController::class)
            ->name('admin.gallery-settings.wallpaper');
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
        Route::put('/admin/kertas-doa/cek-cepat/pengaturan-tulisan', PrayerPaperTextSettingController::class)
            ->name('admin.prayer-paper-preview.text-settings.update');
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

    Route::middleware('role:PRINTER')->group(function () {
        Route::get('/printer', PrinterDashboardController::class)
            ->name('printer.dashboard');
        Route::get('/printer/export', [PrinterDashboardController::class, 'export'])
            ->name('printer.export');
        Route::put('/printer/booking/{booking}/print', BookingPrintedController::class)
            ->name('printer.bookings.print');
        Route::get('/printer/kertas-doa/cek-cepat', PrayerPaperPreviewController::class)
            ->name('printer.prayer-paper-preview');
        Route::get('/printer/kertas-doa/cek-cepat/download', PrayerPaperPreviewDownloadController::class)
            ->name('printer.prayer-paper-preview.download');
        Route::get('/printer/kertas-doa/{prayerPaper}', PrinterPrayerPaperFileController::class)
            ->name('printer.prayer-papers.show');
    });

    Route::middleware('role:CONTENT_TEAM')->group(function () {
        Route::get('/content', ContentDashboardController::class)
            ->name('content.dashboard');
        Route::get('/content/media/global', [GlobalMediaController::class, 'index'])
            ->name('content.global-media.index');
        Route::post('/content/media/global/uploads', [GlobalMediaUploadController::class, 'store'])
            ->middleware('throttle:gallery-uploads')
            ->name('content.global-media.uploads.store');
        Route::post('/content/media/global/{media}/parts', [GlobalMediaUploadController::class, 'signPart'])
            ->middleware('throttle:gallery-upload-parts')
            ->name('content.global-media.parts.store');
        Route::post('/content/media/global/{media}/complete', [GlobalMediaUploadController::class, 'complete'])
            ->middleware('throttle:gallery-uploads')
            ->name('content.global-media.uploads.complete');
        Route::patch('/content/media/global/{media}', [GlobalMediaController::class, 'update'])
            ->middleware('throttle:gallery-mutations')
            ->name('content.global-media.update');
        Route::patch('/content/media/global/{media}/status', [GlobalMediaController::class, 'changeStatus'])
            ->middleware('throttle:gallery-mutations')
            ->name('content.global-media.status');
        Route::delete('/content/media/global/{media}', [GlobalMediaController::class, 'destroy'])
            ->middleware('throttle:gallery-mutations')
            ->name('content.global-media.destroy');
        Route::put('/content/media/global-order', GlobalMediaOrderController::class)
            ->middleware('throttle:gallery-mutations')
            ->name('content.global-media.order');
        Route::get('/content/media/customer', [CustomerMediaController::class, 'index'])
            ->middleware('throttle:gallery-mutations')
            ->name('content.customer-media.index');
        Route::post('/content/media/customer/{booking}/uploads', [CustomerMediaUploadController::class, 'store'])
            ->middleware('throttle:gallery-uploads')
            ->name('content.customer-media.uploads.store');
        Route::post('/content/media/customer/{booking}/{media}/parts', [CustomerMediaUploadController::class, 'signPart'])
            ->middleware('throttle:gallery-upload-parts')
            ->name('content.customer-media.parts.store');
        Route::post('/content/media/customer/{booking}/{media}/complete', [CustomerMediaUploadController::class, 'complete'])
            ->middleware('throttle:gallery-uploads')
            ->name('content.customer-media.uploads.complete');
        Route::patch('/content/media/customer/{booking}/{media}', [CustomerMediaController::class, 'update'])
            ->middleware('throttle:gallery-mutations')
            ->name('content.customer-media.update');
        Route::patch('/content/media/customer/{booking}/{media}/status', [CustomerMediaController::class, 'changeStatus'])
            ->middleware('throttle:gallery-mutations')
            ->name('content.customer-media.status');
        Route::delete('/content/media/customer/{booking}/{media}', [CustomerMediaController::class, 'destroy'])
            ->middleware('throttle:gallery-mutations')
            ->name('content.customer-media.destroy');
        Route::put('/content/media/customer/{booking}/order', CustomerMediaOrderController::class)
            ->middleware('throttle:gallery-mutations')
            ->name('content.customer-media.order');
    });
});

Route::get('/media/paket/{package}', [PackageImageController::class, 'show'])
    ->name('packages.image.show');
