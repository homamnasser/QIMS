<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/



Route::post('/loginUser', [AuthController::class, 'loginUser']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
], function ($router) {
    Route::post('/createStaffMember', [AuthController::class, 'createStaffMember']);
    Route::post('/updateStaffMember/{id}', [AuthController::class, 'updateStaffMember']);
});


Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'role'
], function ($router) {
    Route::post('/createRole', [RoleController::class, 'createRole']);
    Route::get('/getAllRoles', [RoleController::class, 'getAllRoles']);
    Route::get('/getRole/{id}', [RoleController::class, 'getRole']);
    Route::post('/updateRole/{id}', [RoleController::class, 'updateRole']);
    Route::delete('/deleteRole/{id}', [RoleController::class, 'deleteRole']);
});
