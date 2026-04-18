<?php

namespace App\Http\Controllers;

use App\Models\UserMembershipPlan;
use Illuminate\Http\Request;

class UserMembershipPlanController extends BaseApiController
{

    /**
     * Display a listing of the membership plans.
     */
    public function index()
    {
        $plans = UserMembershipPlan::with(['user', 'service'])->paginate(10);

        return $this->paginateResponse(
            success: true,
            message: 'Membership plans retrieved successfully.',
            items: $plans
        );
    }

    /**
     * Store a newly created membership plan in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:services,id',
            'price' => 'required|numeric|min:0',
            'frequency' => 'required|in:monthly,yearly',
            'status' => 'required|in:active,cancelled',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $membershipPlan = UserMembershipPlan::create($validated);

        return $this->response(
            success: true,
            message: 'Membership plan created successfully.',
            data: $membershipPlan,
            statusCode: 201
        );
    }

    /**
     * Display the specified membership plan.
     */
    public function show(UserMembershipPlan $userMembershipPlan)
    {
        return $this->response(
            success: true,
            message: 'Membership plan retrieved successfully.',
            data: $userMembershipPlan->load(['user', 'service'])
        );
    }

    /**
     * Update the specified membership plan in storage.
     */
    public function update(Request $request, UserMembershipPlan $userMembershipPlan)
    {
        $validated = $request->validate([
            'price' => 'numeric|min:0',
            'frequency' => 'in:monthly,yearly',
            'status' => 'in:active,cancelled',
            'start_date' => 'date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $userMembershipPlan->update($validated);

        return $this->response(
            success: true,
            message: 'Membership plan updated successfully.',
            data: $userMembershipPlan
        );
    }

    /**
     * Remove the specified membership plan from storage.
     */
    public function destroy(UserMembershipPlan $userMembershipPlan)
    {
        $userMembershipPlan->delete();

        return $this->response(
            success: true,
            message: 'Membership plan deleted successfully.',
            statusCode: 204
        );
    }

    /**
     * Get membership plans for a specific user.
     */
    public function getUserMembershipPlans($userId)
    {
        $plans = UserMembershipPlan::where('user_id', $userId)->with('service')->paginate(10);

        return $this->paginateResponse(
            success: true,
            message: 'Membership plans for the user retrieved successfully.',
            items: $plans
        );
    }
}
