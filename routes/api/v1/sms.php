<?php
// routes/api/v1/sms.php
use App\Http\Controllers\SmsUsers\FavoritSmsController;
use App\Http\Controllers\SmsUsers\RevisionMessageController;
use App\Http\Controllers\SmsUsers\SmsController;
use App\Http\Controllers\SmsUsers\AnalyticsController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\SmsUsers\OutboxController;
use App\Http\Controllers\SmsUsers\RefactoredSmsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'active', 'lang', 'auth.access'])
    ->group(function () {


        Route::prefix('workspaces/{workspace}/sms')
            ->group(function () {
                // Favorite SMS Messages
                Route::prefix('messages/favorites')->group(function () {
                    Route::get('/', [FavoritSmsController::class, 'index']);
                    Route::post('/', [FavoritSmsController::class, 'store']);
                    Route::get('/{id}', [FavoritSmsController::class, 'show']);
                    Route::put('/{id}', [FavoritSmsController::class, 'update']);
                    Route::delete('/{id}', [FavoritSmsController::class, 'destroy']);
                    Route::delete('/bulk-delete', [FavoritSmsController::class, 'deleteSelected'])->name('sms.messages.favorites.bulkDelete');
                });
                // Revision SMS Messages
                Route::prefix('messages/revision')->group(function () {
                    Route::get('/', [RevisionMessageController::class, 'index']);
                    Route::post('/', [RevisionMessageController::class, 'store']);
                    Route::get('/{id}', [RevisionMessageController::class, 'show']);
                    Route::put('/{id}', [RevisionMessageController::class, 'update']);
                    Route::delete('/{id}', [RevisionMessageController::class, 'destroy']);
                    Route::get('/accept/{id}', [RevisionMessageController::class, 'accept'])->name('message.accept');
                    Route::get('/reject/{id}', [RevisionMessageController::class, 'reject'])->name('message.reject');
                });

                // Regular SMS Messages
                Route::prefix('messages')->group(function () {
                    Route::delete('/bulk-delete', [SmsController::class, 'deleteSelected']);
                    Route::get('/export/{message?}', [SmsController::class, 'export'])->name('sms.messages.export');
                    Route::post('/upload', [SmsController::class, 'upload_excel'])->name('sms.upload.excel');
                    Route::get('/', [SmsController::class, 'index']);
                    Route::post('/', [SmsController::class, 'store']);
                    Route::get('/{message}', [SmsController::class, 'show']);
                    Route::put('/{message}', [SmsController::class, 'update']);
                    Route::delete('/{message}', [SmsController::class, 'destroy']);
                });
            });
    });





Route::middleware(['auth.access'])
    ->controller(MessagesController::class)
    ->prefix('/workspaces/{workspace}/channels/{channel}')
    ->group(function () {
        Route::post('/messages', 'sendMessage');
        Route::post('/statistics', 'statisticsMessage');
          Route::get('sms/statistics/status', [SmsController::class, 'checkStatisticsStatus'])->name('sms.statistics.status');
        Route::post('sms/statistics/approve/{processingId}', [RefactoredSmsController::class, 'approveStatistics'])->name('sms.statistics.approve');
        Route::post('sms/statistics/reject/{processingId}', [RefactoredSmsController::class, 'rejectStatistics'])->name('sms.statistics.reject');
        Route::get('sms/statistics/review/{processingId}', [RefactoredSmsController::class, 'reviewStatistics'])->name('sms.statistics.review');

    });
Route::middleware((['auth:api']))
    ->prefix('SmsUsers')
    ->group(function () {
        Route::post('sms/upload', [SmsController::class, 'upload_excel'])->name('sms.upload');
        Route::post('sms/send-from-approved', [SmsController::class, 'sendFromApprovedStatistics'])->name('sms.send.from.approved');

        // Background statistics processing routes
        // Route::get('sms/statistics/status', [SmsController::class, 'checkStatisticsStatus'])->name('sms.statistics.status');
        // Route::post('sms/statistics/approve', [SmsController::class, 'approveStatistics'])->name('sms.statistics.approve');
    });
Route::middleware((['auth:api']))
    ->prefix('SmsUsers')
    ->group(function () {
        Route::get('outbox', [OutboxController::class, 'index'])->name('outbox.index');
    });
