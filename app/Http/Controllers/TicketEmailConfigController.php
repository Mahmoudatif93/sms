<?php

namespace App\Http\Controllers;

use App\Models\TicketEmailConfiguration;
use App\Models\Workspace;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TicketEmailConfigController extends Controller
{
    /**
     * Display a listing of the email configurations.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            $workspace = $user->currentWorkspace();
            
            $configs = TicketEmailConfiguration::where('workspace_id', $workspace->id)->get();
            
            return response()->json([
                'success' => true,
                'data' => $configs,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching email configurations: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email configurations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Store a newly created email configuration in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request,Workspace $workspace): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email_address' => 'required|email|max:255|unique:ticket_email_configurations,email_address',
                'mail_server' => 'required|string|max:255',
                'mail_port' => 'required|integer|min:1|max:65535',
                'mail_username' => 'required|string|max:255',
                'mail_password' => 'required|string',
                'mail_encryption' => 'required|string|in:tls,ssl,',
                'is_active' => 'boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            $user = Auth::user();
            $workspace = $user->currentWorkspace();
            
            // Create the email configuration
            $config = new TicketEmailConfiguration([
                'workspace_id' => $workspace->id,
                'email_address' => $request->input('email_address'),
                'mail_server' => $request->input('mail_server'),
                'mail_port' => $request->input('mail_port'),
                'mail_username' => $request->input('mail_username'),
                'mail_password' => $request->input('mail_password'),
                'mail_encryption' => $request->input('mail_encryption'),
                'is_active' => $request->boolean('is_active', true),
            ]);
            
            $config->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Email configuration created successfully',
                'data' => $config,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating email configuration: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->except(['mail_password']),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Display the specified email configuration.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $workspace = $user->currentWorkspace();
            
            $config = TicketEmailConfiguration::where('workspace_id', $workspace->id)
                ->where('id', $id)
                ->firstOrFail();
                
            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        } catch (Exception $e) {
            Log::error('Error fetching email configuration: ' . $e->getMessage(), [
                'exception' => $e,
                'config_id' => $id,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Update the specified email configuration in storage.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email_address' => "sometimes|required|email|max:255|unique:ticket_email_configurations,email_address,{$id}",
                'mail_server' => 'sometimes|required|string|max:255',
                'mail_port' => 'sometimes|required|integer|min:1|max:65535',
                'mail_username' => 'sometimes|required|string|max:255',
                'mail_password' => 'sometimes|required|string',
                'mail_encryption' => 'sometimes|required|string|in:tls,ssl,',
                'is_active' => 'boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            $user = Auth::user();
            $workspace = $user->currentWorkspace();
            
            $config = TicketEmailConfiguration::where('workspace_id', $workspace->id)
                ->where('id', $id)
                ->firstOrFail();
                
            // Update fields if provided
            if ($request->has('email_address')) {
                $config->email_address = $request->input('email_address');
            }
            
            if ($request->has('mail_server')) {
                $config->mail_server = $request->input('mail_server');
            }
            
            if ($request->has('mail_port')) {
                $config->mail_port = $request->input('mail_port');
            }
            
            if ($request->has('mail_username')) {
                $config->mail_username = $request->input('mail_username');
            }
            
            if ($request->has('mail_password')) {
                $config->mail_password = $request->input('mail_password');
            }
            
            if ($request->has('mail_encryption')) {
                $config->mail_encryption = $request->input('mail_encryption');
            }
            
            if ($request->has('is_active')) {
                $config->is_active = $request->boolean('is_active');
            }
            
            $config->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Email configuration updated successfully',
                'data' => $config,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        } catch (Exception $e) {
            Log::error('Error updating email configuration: ' . $e->getMessage(), [
                'exception' => $e,
                'config_id' => $id,
                'request' => $request->except(['mail_password']),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Remove the specified email configuration from storage.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $workspace = $user->currentWorkspace();
            
            $config = TicketEmailConfiguration::where('workspace_id', $workspace->id)
                ->where('id', $id)
                ->firstOrFail();
                
            $config->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Email configuration deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        } catch (Exception $e) {
            Log::error('Error deleting email configuration: ' . $e->getMessage(), [
                'exception' => $e,
                'config_id' => $id,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Test the connection to the mail server.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'mail_server' => 'required|string|max:255',
                'mail_port' => 'required|integer|min:1|max:65535',
                'mail_username' => 'required|string|max:255',
                'mail_password' => 'required|string',
                'mail_encryption' => 'required|string|in:tls,ssl,',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            // Set up IMAP client for testing
            $config = [
                'host' => $request->input('mail_server'),
                'port' => $request->input('mail_port'),
                'encryption' => $request->input('mail_encryption'),
                'validate_cert' => true,
                'username' => $request->input('mail_username'),
                'password' => $request->input('mail_password'),
                'protocol' => 'imap',
            ];
            
            // Try to connect
            $client = \Webklex\IMAP\Facades\Client::make($config);
            $client->connect();
            
            // Check if connected
            if ($client->isConnected()) {
                // Try to get inbox folder
                $inbox = $client->getFolder('INBOX');
                $messageCount = $inbox->query()->all()->count();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => [
                        'inbox_count' => $messageCount,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to the mail server',
                ], 400);
            }
        } catch (Exception $e) {
            Log::error('Error testing email connection: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->except(['mail_password']),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}