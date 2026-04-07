<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\MosqueController;

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
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin|admin'],
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
    Route::get('/getAllPermissions', [RoleController::class, 'getAllPermissions']);
});


Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'project'
], function ($router) {
    Route::post('/createProject', [ProjectController::class, 'createProject']);
    Route::get('/getAllProjects', [ProjectController::class, 'getAllProjects']);
    Route::get('/getProject/{id}', [ProjectController::class, 'getProject']);
    Route::post('/updateProject/{id}', [ProjectController::class, 'updateProject']);
    Route::delete('/deleteProject/{id}', [ProjectController::class, 'deleteProject']);
    Route::post('/editProjectStatus/{id}', [ProjectController::class, 'editProjectStatus']);
});
Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'mosque'
], function ($router) {
    Route::post('/createMosque', [MosqueController::class, 'createMosque']);
    Route::get('/getAllMosques', [MosqueController::class, 'getAllMosques']);
    Route::get('/getMosque/{id}', [MosqueController::class, 'getMosque']);
    Route::post('/updateMosque/{id}', [MosqueController::class, 'updateMosque']);
    Route::delete('/deleteMosque/{id}', [MosqueController::class, 'deleteMosque']);
});
