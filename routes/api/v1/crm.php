<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Crm\BoardController;
use App\Http\Controllers\Crm\BoardFieldController;
use App\Http\Controllers\Crm\BoardStageController;
use App\Http\Controllers\Crm\BoardTabController;
use App\Http\Controllers\Crm\BoardTagController;
use App\Http\Controllers\Crm\CategoryController;
use App\Http\Controllers\Crm\DealController;
use App\Http\Controllers\Crm\PipelineController;
use App\Http\Controllers\Crm\PipelineFieldController;
use App\Http\Controllers\Crm\PipelineStageController;
use App\Http\Controllers\Crm\PipelineTabController;
use App\Http\Controllers\Crm\ProductController;
use App\Http\Controllers\Crm\TaskController;

//***********CRM*********///
Route::middleware(['api', 'auth:api'])->prefix('crm/')->group(function () {
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
        Route::put('/{product}/toggle-visibility', [ProductController::class, 'toggleVisibility']);
    });

    Route::prefix('pipelines')->group(function () {

        Route::get('/', [PipelineController::class, 'index']); // Get all pipelines
        Route::post('/', [PipelineController::class, 'store']); // Create a new pipeline
        Route::put('{id}', [PipelineController::class, 'update']); // Update a pipeline
        Route::delete('{pipeline}', [PipelineController::class, 'destroy']); // Delete a pipeline
        Route::patch('{pipeline}/assign', [PipelineController::class, 'assignPipeline']); // Assign pipeline to user


        Route::prefix('{pipeline}/tabs')->group(function () {
            Route::get('/', [PipelineTabController::class, 'index']);  // Get all tabs for a board
            Route::post('/', [PipelineTabController::class, 'store']); // Create a new tab for a board
            Route::patch('{tab}/toggle', [PipelineTabController::class, 'toggleEnable']); // Enable/Disable tab
            Route::put('{tab}', [PipelineTabController::class, 'update']); // Update tab
            Route::delete('{tab}', [PipelineTabController::class, 'destroy']); // Delete tab
        });
    });


    Route::prefix('pipeline-fields')->group(function () {
        Route::get('/{pipeline_tab_id}', [PipelineFieldController::class, 'index']);  // List fields for a specific tab
        Route::post('/', [PipelineFieldController::class, 'store']);  // Create a field
        Route::put('/{field}', [PipelineFieldController::class, 'update']);  // Update a field
        Route::delete('/{field}', [PipelineFieldController::class, 'destroy']);  // Delete a field
        Route::patch('/{pipeline_tab_id}/{field_id}/toggle', [PipelineFieldController::class, 'toggleEnable']); // Toggle enable field
    });


    Route::apiResource('pipeline-stages', PipelineStageController::class);
    Route::get('/pipelines/{pipelineId}/stages', [PipelineStageController::class, 'show']);
    Route::prefix('deals')->group(function () {
        Route::get('/', [DealController::class, 'index']);
        Route::post('/', [DealController::class, 'store']);
        Route::post('/{dealId}/history', [DealController::class, 'storeHistory']);
        Route::post('/{deal}/reminders', [DealController::class, 'addReminder']);
        Route::get('/{deal}/reminders', [DealController::class, 'getReminders']);
        Route::patch('/{deal}/status', [DealController::class, 'changeStatus']);
        Route::get('/closed', [DealController::class, 'closedDeals']);
        Route::get('/{deal}', [DealController::class, 'show']);
        Route::put('/{deal}', [DealController::class, 'update']);
        Route::get('/fields/general/{pipeline_id}', [DealController::class, 'generalFields']);
        Route::get('/fields/non-general/{pipeline_id}', [DealController::class, 'nonGeneralFields']);

        Route::delete('/{deal}', [DealController::class, 'destroy']);

        // Delete single deal file
        Route::delete(
            '{deal}/files/{file}',
            [DealController::class, 'deleteFile']
        );
    });

    // Board Management APIs
    Route::prefix('boards')->group(function () {
        Route::get('/', [BoardController::class, 'index']); // Get all boards
        Route::post('/', [BoardController::class, 'store']); // Create a new board
        Route::get('{board}', [BoardController::class, 'show']); // Get a specific board
        Route::put('{board}', [BoardController::class, 'update']); // Update board
        Route::delete('{board}', [BoardController::class, 'destroy']); // Delete board
        Route::patch('{board}/assign', [BoardController::class, 'assignBoard']); // Assign board

        // Board Tabs APIs
        Route::prefix('{board}/tabs')->group(function () {
            Route::get('/', [BoardTabController::class, 'index']); // Get all tabs for a board
            Route::post('/', [BoardTabController::class, 'store']); // Create a new tab
            Route::patch('{tab}/toggle', [BoardTabController::class, 'toggleEnable']); // Enable/Disable tab
            Route::put('{tab}', [BoardTabController::class, 'update']); // Update tab
            Route::delete('{tab}', [BoardTabController::class, 'destroy']); // Delete tab
        });

        // Board Tags APIs
        Route::prefix('{board}/tags')->group(function () {
            Route::get('/', [BoardTagController::class, 'index']); // Get all tags for a board
            Route::post('/', [BoardTagController::class, 'store']); // Create a new tag
            Route::put('{tag}', [BoardTagController::class, 'update']); // Update tag (changed {id} → {tag})
            Route::delete('{tag}', [BoardTagController::class, 'destroy']); // Delete tag (changed {id} → {tag})
        });
    });
    Route::prefix('board-fields')->group(function () {
        Route::get('{boardTab}/', [BoardFieldController::class, 'index']);      // return a BoardField
        Route::post('{boardTab}/', [BoardFieldController::class, 'store']);      // Create a BoardField
        Route::put('{boardTab}/{boardField}', [BoardFieldController::class, 'update']); // Update a BoardField
        Route::delete('{boardTab}/{boardField}', [BoardFieldController::class, 'destroy']); // Delete a BoardField
        Route::patch('{boardTab}/{boardField}/toggle', [BoardFieldController::class, 'toggleEnable']); // Enable/Disable tab
    });
    Route::prefix('board-stages')->group(function () {
        Route::get('/{boardId}', [BoardStageController::class, 'index']); // Get all stages for a board
        Route::post('/', [BoardStageController::class, 'store']); // Create board stage
        Route::put('/{boardStage}', [BoardStageController::class, 'update']); // Update board stage
        Route::delete('/{boardStage}', [BoardStageController::class, 'destroy']); // Delete board stage
    });


    Route::prefix('tasks')->group(function () {
        // Completed tasks
        Route::get('/completed', [TaskController::class, 'completedTasks']);

        // Retrieve tasks
        Route::get('/', [TaskController::class, 'index']); // Get all tasks
        Route::get('{task}', [TaskController::class, 'show']); // Show a single task

        // Task creation & modification
        Route::post('/', [TaskController::class, 'store']); // Create a new task
        Route::put('{task}', [TaskController::class, 'update']); // Update a task

        // Task-specific actions
        Route::post('{task}/change-board-stage', [TaskController::class, 'changeBoardStage']); // Change board stage
        Route::post('{task}/history', [TaskController::class, 'storeHistory']); // Store task history
        Route::patch('{task}/status', [TaskController::class, 'changeStatus']); // Change task status

        // New Routes: General & Non-General Fields
        Route::get('/fields/general/{board_id}', [TaskController::class, 'generalFields']); // Get general fields by board_tab_id
        Route::get('/fields/non-general/{board_id}', [TaskController::class, 'nonGeneralFields']); // Get non-general fields by board_tab_id

        Route::post('/{task}/reminders', [TaskController::class, 'addReminder']);
        Route::get('/{task}/reminders', [TaskController::class, 'getReminders']);

        Route::post('/{task}/observers/add', [TaskController::class, 'addObservers']);
        Route::post('/{task}/observers/remove', [TaskController::class, 'removeObservers']);
        // Delete task
        Route::delete('{task}', [TaskController::class, 'destroy']); // Delete a task

        Route::get('/board/{board_id}/tasks', [TaskController::class, 'getBoardTasks']);
        // Delete single deal file

        Route::delete('{task}/files/{file}', [TaskController::class, 'deleteFile']);
    });
});
