<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SmsApiController;
use App\Models\OrganizationPlan;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Exports\PlanSheetExport;
use Maatwebsite\Excel\Facades\Excel;
class plansController extends SmsApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('check.admin'),
        ];
    }
    public function index(Request $request)
    {
            $perPage = $request->get('per_page', 15); // Default to 15 if not provided
            $page = $request->get('page', 1);
            // Set the current page for the paginator
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });
            // Fetch paginated dataa
            $search = $request->search ?? null;
            $items = Plan::load_data(null, $perPage, $search, true,0);

            // Customize the response
            return response()->json([
                'data' => $items->items(),
                'pagination' => [
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                ],
            ]);

    }

    public function store(Request $request)
    {
        $rules = [
            'points_cnt' => 'required|string|max:10',
            'price' => 'required|numeric|max:99999999.99',
            'currency' => 'required|string|max:5',
            'method' => 'required|string|max:20',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $data = $request->all();
        $data['created_by_id'] = auth('admin')->user()->id;
        $data['dealer_id'] = null;
        $data['created_by_type'] = 'admin';
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['price'] = $request->price;
        $data['points_cnt'] = $request->points_cnt;
        $data['is_active'] = 1;
        $plan = Plan::create($data);
        //////organizations
        $plan->connectToAllOrganizations();

        return $this->response(true, ' plan', $plan);
    }

    public function destroy($id)
    {
        $plan = Plan::find($id);
        if (!$plan) {
            return $this->response(false, 'This Plan does not exists! ', null, 401);
        }
        $plan->delete();
        return $this->response(true, __('message.msg_delete_row'));
    }



    public function exportPlan(Request $request)
    {

        $search = $request->search ?? null;
        $query = Plan::load_data(null, null, $search, true,0);
        // Convert the result into the expected response format
        $totalRecords = $query->count();
        $fileName     = 'plan_export_' . time() . '.xlsx';
       return Excel::download(new PlanSheetExport($query), $fileName);
    }
}
