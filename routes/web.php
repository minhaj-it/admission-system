<?php

use App\Http\Controllers\Admin\ApplicantController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CircularController;
use App\Http\Controllers\Admin\CircularOfferingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InstituteController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\Student\PaymentController as StudentPaymentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Student\ApplicationController as StudentApplicationController;
use App\Http\Controllers\Student\HomeController as StudentHomeController;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::middleware(SecurityHeaders::class)->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/branding/logo', [BrandingController::class, 'logo'])->name('branding.logo');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store'); // throttled inside LoginRequest

        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');

        Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');

        Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        // authorised per request by ApplicationPolicy (owner student, or staff with applicant-list.view)
        Route::get('/applications/{application}/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('/payments/{payment}/receipt', [ReceiptController::class, 'show'])->name('payments.receipt');

        Route::middleware(EnsureAccountType::class . ':staff')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

            Route::prefix('settings')->group(function () {
                Route::get('institute', [InstituteController::class, 'edit'])->middleware('can:institute.view')->name('institute.edit');
                Route::put('institute', [InstituteController::class, 'update'])->middleware('can:institute.edit')->name('institute.update');

                Route::get('master/{type?}', [MasterDataController::class, 'index'])->whereIn('type', \App\Support\MasterData::keys())->middleware('can:classes.view')->name('master.index');
                Route::post('master/{type}', [MasterDataController::class, 'store'])->whereIn('type', \App\Support\MasterData::keys())->middleware('can:classes.add')->name('master.store');
                Route::put('master/{type}/{id}', [MasterDataController::class, 'update'])->whereIn('type', \App\Support\MasterData::keys())->whereNumber('id')->middleware('can:classes.edit')->name('master.update');
                Route::patch('master/{type}/{id}/toggle', [MasterDataController::class, 'toggle'])->whereIn('type', \App\Support\MasterData::keys())->whereNumber('id')->middleware('can:classes.edit')->name('master.toggle');
                Route::delete('master/{type}/{id}', [MasterDataController::class, 'destroy'])->whereIn('type', \App\Support\MasterData::keys())->whereNumber('id')->middleware('can:classes.del')->name('master.destroy');

                Route::get('roles', [RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
                Route::post('roles', [RoleController::class, 'store'])->middleware('can:roles.add')->name('roles.store');
                Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.edit')->name('roles.update');
                Route::put('roles/{role}/permissions', [RoleController::class, 'permissions'])->middleware('can:roles.edit')->name('roles.permissions');
                Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.del')->name('roles.destroy');

                Route::get('users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
                Route::post('users', [UserController::class, 'store'])->middleware('can:users.add')->name('users.store');
                Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:users.edit')->name('users.update');
                Route::patch('users/{user}/activate', [UserController::class, 'activate'])->middleware('can:users.edit')->name('users.activate');
                Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('can:users.edit')->name('users.deactivate');
            });

            Route::get('circulars', [CircularController::class, 'index'])->middleware('can:circular.view')->name('circulars.index');
            Route::post('circulars', [CircularController::class, 'store'])->middleware('can:circular.add')->name('circulars.store');
            Route::get('circulars/{circular}', [CircularController::class, 'show'])->middleware('can:circular.view')->name('circulars.show');
            Route::put('circulars/{circular}', [CircularController::class, 'update'])->middleware('can:circular.edit')->name('circulars.update');
            Route::delete('circulars/{circular}', [CircularController::class, 'destroy'])->middleware('can:circular.del')->name('circulars.destroy');
            Route::patch('circulars/{circular}/archive', [CircularController::class, 'archive'])->middleware('can:circular.edit')->name('circulars.archive');
            Route::patch('circulars/{circular}/restore', [CircularController::class, 'restore'])->middleware('can:circular.edit')->name('circulars.restore');
            Route::put('circulars/{circular}/downloads', [CircularController::class, 'downloads'])->middleware('can:circular.edit')->name('circulars.downloads');
            Route::post('circulars/{circular}/offerings', [CircularOfferingController::class, 'store'])->middleware('can:circular.edit')->name('circulars.offerings.store');

            Route::get('applicants', [ApplicantController::class, 'index'])->middleware('can:applicant-list.view')->name('applicants.index');
            Route::get('applicants/{application}', [ApplicantController::class, 'show'])->middleware('can:applicant-list.view')->name('applicants.show');
            Route::patch('applicants/{application}/status', [ApplicantController::class, 'status'])->middleware('can:applicant.status')->name('applicants.status');
            Route::patch('applicants/{application}/documents/{document}', [ApplicantController::class, 'document'])->middleware('can:document.verify')->name('applicants.document');

            Route::get('payments', [AdminPaymentController::class, 'index'])->middleware('can:payments.view')->name('payments.index');
            Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->middleware('can:payments.view')->name('payments.show');
            Route::patch('payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->middleware('can:payments.verify')->name('payments.approve');
            Route::patch('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->middleware('can:payments.verify')->name('payments.reject');
            Route::patch('payments/{payment}/recheck', [AdminPaymentController::class, 'recheck'])->middleware('can:payments.verify')->name('payments.recheck');

            Route::get('settings/payment-methods', [PaymentMethodController::class, 'index'])->middleware('can:payment-method.view')->name('payment-methods.index');
            Route::patch('settings/payment-methods/{method}/activate', [PaymentMethodController::class, 'activate'])->middleware('can:payment-method.edit')->name('payment-methods.activate');
            Route::patch('settings/payment-methods/{method}/deactivate', [PaymentMethodController::class, 'deactivate'])->middleware('can:payment-method.edit')->name('payment-methods.deactivate');
            Route::put('settings/payment-methods/{method}/instructions', [PaymentMethodController::class, 'instructions'])->middleware('can:payment-method.edit')->name('payment-methods.instructions');

            Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit.index');
        });

        Route::middleware(EnsureAccountType::class . ':student')->prefix('student')->name('student.')->group(function () {
            Route::get('/', StudentHomeController::class)->name('home');

            Route::post('applications', [StudentApplicationController::class, 'start'])->middleware('throttle:20,1')->name('applications.start');
            Route::get('applications/{application}', [StudentApplicationController::class, 'show'])->name('applications.show');
            Route::get('applications/{application}/step/{step}', [StudentApplicationController::class, 'step'])->whereIn('step', [1, 2, 3, 4, 5])->name('applications.step');
            Route::put('applications/{application}/step/{step}', [StudentApplicationController::class, 'saveStep'])->whereIn('step', [1, 2, 3, 4])->name('applications.step.save');
            Route::get('applications/{application}/pay', [StudentPaymentController::class, 'create'])->name('payments.create');
            Route::post('applications/{application}/pay/manual', [StudentPaymentController::class, 'manual'])->middleware('throttle:10,1')->name('payments.manual');
            Route::post('applications/{application}/pay/gateway', [StudentPaymentController::class, 'gateway'])->middleware('throttle:10,1')->name('payments.gateway');
            Route::get('payments/shurjopay/return', [StudentPaymentController::class, 'shurjopayReturn'])->middleware('throttle:30,1')->name('payments.shurjopay.return');
            Route::get('payments/shurjopay/cancel', [StudentPaymentController::class, 'shurjopayCancel'])->middleware('throttle:30,1')->name('payments.shurjopay.cancel');

            Route::post('applications/{application}/submit', [StudentApplicationController::class, 'submit'])->middleware('throttle:10,1')->name('applications.submit');
        });
    });
});
