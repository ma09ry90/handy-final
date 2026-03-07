<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ArtisanProfileController;
use App\Http\Controllers\Admin\ProfileApprovalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Enjoy building your API!
|
*/

// ==================
// PUBLIC ROUTES
// ==================
// These routes do not require authentication.

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register/buyer', [AuthController::class, 'registerBuyer']);
Route::post('/register/artisan', [AuthController::class, 'registerArtisan']);
Route::post('/register/delivery', [AuthController::class, 'registerDelivery']);


// ==================
// PROTECTED ROUTES
// ==================
// These routes require a valid Bearer Token (Sanctum) and an Active Account.

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    
    // --- Authentication & User Info ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // --- Admin Routes ---
    // Middleware 'role:admin' ensures only users with role_id 4 can access.
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        
        // Dashboard Data
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Profile Approval Actions
        // URL Example: POST /api/admin/approve/artisan/5
        // URL Example: POST /api/admin/reject/delivery/10
        Route::post('/approve/{type}/{id}', [ProfileApprovalController::class, 'approve']);
        Route::post('/reject/{type}/{id}', [ProfileApprovalController::class, 'reject']);
    });

    // --- Artisan Routes ---
    // Middleware 'role:artisan' ensures only users with role_id 2 can access.
    Route::middleware('role:artisan')->prefix('artisan')->group(function () {
        
        // Artisan Profile Management
        Route::get('/profile', [ArtisanProfileController::class, 'edit']);
        Route::put('/profile', [ArtisanProfileController::class, 'update']);
    });

    // --- Delivery Routes ---
    // (Add delivery specific routes here later if needed)
    // Route::middleware('role:delivery')->prefix('delivery')->group(function () {
    //    ...
    // });

});