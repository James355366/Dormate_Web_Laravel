<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RoomController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Named routes replace index.php?page=... and run through Laravel's web middleware.
Route::get('/', [PageController::class, 'show'])->defaults('page', 'home')->name('dormate.home');
Route::post('/', [ActionController::class, 'store'])->defaults('page', 'home');
Route::get('/login', [PageController::class, 'show'])->defaults('page', 'login')->name('dormate.login');
Route::post('/login', [ActionController::class, 'store'])->defaults('page', 'login');
Route::get('/register', [PageController::class, 'show'])->defaults('page', 'register')->name('dormate.register');
Route::post('/register', [ActionController::class, 'store'])->defaults('page', 'register');
Route::get('/forgot-password', [PageController::class, 'show'])->defaults('page', 'forgot')->name('dormate.forgot');
Route::post('/forgot-password', [ActionController::class, 'store'])->defaults('page', 'forgot');
Route::get('/reset-password', [PageController::class, 'show'])->defaults('page', 'reset')->name('dormate.reset');
Route::post('/reset-password', [ActionController::class, 'store'])->defaults('page', 'reset');
Route::get('/pending', [PageController::class, 'show'])->defaults('page', 'pending')->name('dormate.pending');
Route::post('/pending', [ActionController::class, 'store'])->defaults('page', 'pending');
Route::get('/dashboard', [PageController::class, 'show'])->defaults('page', 'dashboard')->name('dormate.dashboard');
Route::post('/dashboard', [ActionController::class, 'store'])->defaults('page', 'dashboard');
Route::get('/rooms', [RoomController::class, 'show'])->defaults('page', 'rooms')->name('dormate.rooms');
Route::post('/rooms', [ActionController::class, 'store'])->defaults('page', 'rooms');
Route::get('/rooms/edit', [PageController::class, 'show'])->defaults('page', 'room_form')->name('dormate.room_form');
Route::post('/rooms/edit', [ActionController::class, 'store'])->defaults('page', 'room_form');
Route::get('/residents', [PageController::class, 'show'])->defaults('page', 'residents')->name('dormate.residents');
Route::post('/residents', [ActionController::class, 'store'])->defaults('page', 'residents');
Route::get('/residents/view', [PageController::class, 'show'])->defaults('page', 'resident')->name('dormate.resident');
Route::post('/residents/view', [ActionController::class, 'store'])->defaults('page', 'resident');
Route::get('/accounts/create', [PageController::class, 'show'])->defaults('page', 'account_form')->name('dormate.account_form');
Route::post('/accounts/create', [ActionController::class, 'store'])->defaults('page', 'account_form');
Route::get('/users', [PageController::class, 'show'])->defaults('page', 'users')->name('dormate.users');
Route::post('/users', [ActionController::class, 'store'])->defaults('page', 'users');
Route::get('/team/edit', [PageController::class, 'show'])->defaults('page', 'team')->name('dormate.team');
Route::post('/team/edit', [ActionController::class, 'store'])->defaults('page', 'team');
Route::get('/bills', [PageController::class, 'show'])->defaults('page', 'bills')->name('dormate.bills');
Route::post('/bills', [ActionController::class, 'store'])->defaults('page', 'bills');
Route::get('/bills/create', [PageController::class, 'show'])->defaults('page', 'bill_form')->name('dormate.bill_form');
Route::post('/bills/create', [ActionController::class, 'store'])->defaults('page', 'bill_form');
Route::get('/bills/view', [PageController::class, 'show'])->defaults('page', 'bill')->name('dormate.bill');
Route::post('/bills/view', [ActionController::class, 'store'])->defaults('page', 'bill');
Route::get('/maintenance', [PageController::class, 'show'])->defaults('page', 'maintenance')->name('dormate.maintenance');
Route::post('/maintenance', [ActionController::class, 'store'])->defaults('page', 'maintenance');
Route::get('/maintenance/create', [PageController::class, 'show'])->defaults('page', 'report_form')->name('dormate.report_form');
Route::post('/maintenance/create', [ActionController::class, 'store'])->defaults('page', 'report_form');
Route::get('/maintenance/view', [PageController::class, 'show'])->defaults('page', 'report')->name('dormate.report');
Route::post('/maintenance/view', [ActionController::class, 'store'])->defaults('page', 'report');
Route::get('/announcements', [PageController::class, 'show'])->defaults('page', 'announcements')->name('dormate.announcements');
Route::post('/announcements', [ActionController::class, 'store'])->defaults('page', 'announcements');
Route::get('/announcements/edit', [PageController::class, 'show'])->defaults('page', 'post_form')->name('dormate.post_form');
Route::post('/announcements/edit', [ActionController::class, 'store'])->defaults('page', 'post_form');
Route::get('/profile', [PageController::class, 'show'])->defaults('page', 'profile')->name('dormate.profile');
Route::post('/profile', [ActionController::class, 'store'])->defaults('page', 'profile');
Route::get('/password', [PageController::class, 'show'])->defaults('page', 'password')->name('dormate.password');
Route::post('/password', [ActionController::class, 'store'])->defaults('page', 'password');
Route::get('/settings', [PageController::class, 'show'])->defaults('page', 'settings')->name('dormate.settings');
Route::post('/settings', [ActionController::class, 'store'])->defaults('page', 'settings');
Route::get('/contact', [PageController::class, 'show'])->defaults('page', 'contact')->name('dormate.contact');
Route::post('/contact', [ActionController::class, 'store'])->defaults('page', 'contact');
Route::get('/staff-requests', [PageController::class, 'show'])->defaults('page', 'staff_requests')->name('dormate.staff_requests');
Route::post('/staff-requests', [ActionController::class, 'store'])->defaults('page', 'staff_requests');

Route::get('/media', [MediaController::class, 'show'])->name('media');
Route::get('/index.php', function (Request $request) {
    $page = (string) $request->query('page', 'home');
    abort_unless(Route::has('dormate.'.$page), 404);

    return redirect()->route('dormate.'.$page, $request->except('page'));
});
