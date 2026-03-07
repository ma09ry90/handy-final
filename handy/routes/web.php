<?php


use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| These routes are now redundant because your Frontend is on PC 3.
| You can leave this file empty. 
|
*/

Route::get('/', function () {
    return response()->json(['message' => 'Backend API is running.']);
});
/*use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\ProfileApprovalController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('LandingPage')
        // THIS IS THE FIX: Force light mode for the landing page
        ->withViewData(['appearance' => 'light']);
})->name('home');

// Change 'Auth/Login' to 'auth/Login'
Route::get('/login', function () {
    return Inertia::render('auth/Login');
})->name('login');

// 2. POST Route: Handles the actual login and redirection logic.
Route::post('/login', [AuthController::class, 'login']);

// Admin Routes (Protected by 'role:admin' middleware)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Dashboard View
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    
    // Approval Actions (Using your existing ProfileApprovalController)
    // We use ->defaults() to inject 'artisan' as the $type argument
    Route::post('/artisans/{id}/approve', [ProfileApprovalController::class, 'approve'])->defaults('type', 'artisan')->name('artisans.approve');
    Route::post('/artisans/{id}/reject', [ProfileApprovalController::class, 'reject'])->defaults('type', 'artisan')->name('artisans.reject');
    
    // View Artisan Details (Optional, if you have a specific page for this)
    Route::get('/artisans/{id}', function ($id) {
        $profile = \App\Models\ArtisanProfile::with(['user', 'identityDocument', 'businessLicenseDocument', 'taxRegistrationDocument'])->findOrFail($id);
        return Inertia::render('admin/ArtisanDetail', ['profile' => $profile]);
    })->name('artisans.show');
});

// Change 'Auth/Register/BuyerRegister' to 'auth/register/BuyerRegister'
Route::get('/register/buyer', function () {
    return Inertia::render('auth/register/BuyerRegister');
})->name('register.buyer');

// Change 'Auth/Register/ArtisanRegister' to 'auth/register/ArtisanRegister'
// Show the form
Route::get('/register/artisan', function () {
    return Inertia::render('auth/register/ArtisanRegister');
})->name('register.artisan');

// Submit the form
Route::post('/register/artisan', [AuthController::class, 'registerArtisan']);

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

// Load API routes with the 'api' prefix
Route::prefix('api')->group(function () {
    require __DIR__.'/api.php';
});*/