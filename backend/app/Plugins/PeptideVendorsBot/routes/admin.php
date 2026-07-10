<?php

use App\Plugins\PeptideVendorsBot\Controllers\Admin\PeptideBotController;
use Illuminate\Support\Facades\Route;

Route::prefix('peptide-vendors')->name('peptide-vendors.')->group(function () {
    Route::get('/status', [PeptideBotController::class, 'status'])->name('status');
    Route::get('/welcome-messages', [PeptideBotController::class, 'welcomeMessages'])->name('welcome-messages');
    Route::get('/webhook-updates', [PeptideBotController::class, 'webhookUpdates'])->name('webhook-updates');

    Route::get('/topics', [PeptideBotController::class, 'topics'])->name('topics');
    Route::get('/verifications', [PeptideBotController::class, 'verifications'])->name('verifications');
    Route::get('/questions', [PeptideBotController::class, 'questions'])->name('questions');
    Route::post('/questions', [PeptideBotController::class, 'storeQuestion'])->name('questions.store');
    Route::put('/questions/{id}', [PeptideBotController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('/questions/{id}', [PeptideBotController::class, 'deleteQuestion'])->name('questions.delete');
    Route::get('/permissions', [PeptideBotController::class, 'permissions'])->name('permissions');
    Route::post('/permissions', [PeptideBotController::class, 'storePermission'])->name('permissions.store');
    Route::delete('/permissions/{id}', [PeptideBotController::class, 'deletePermission'])->name('permissions.delete');
    Route::post('/iptv-lines', [PeptideBotController::class, 'storeIptvLine'])->name('iptv-lines.store');
    Route::put('/iptv-lines/{id}', [PeptideBotController::class, 'updateIptvLine'])->name('iptv-lines.update');
    Route::get('/iptv-lines', [PeptideBotController::class, 'iptvLines'])->name('iptv-lines');
    Route::post('/permissions/clear-topic', [PeptideBotController::class, 'deleteTopicPermissions'])->name('permissions.clear-topic');
});
