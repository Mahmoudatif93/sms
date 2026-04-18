<?php
namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table    = 'plans'; // Replace with your actual table name
    protected $fillable = [
        'points_cnt',
        'price',
        'currency',
        'method',
        'dealer_id',
        'is_active',
        'created_by_id',
        'created_by_type',
    ];

    public static function load_data($user_id, $perPage, $search = null, $withUserJoin = true, $active = 1)
    {
        // Base query
        $query = Plan::select(
            'plans.id',
            'plans.points_cnt',
            'plans.price',
            'plans.currency',
            'plans.method',
            'plans.dealer_id',
            'plans.created_at',
            'plans.updated_at',
            'plans.created_by_id',
            'plans.created_by_type',
            'plans.is_active'
        )
            ->whereNull('plans.deleted_at');

        if ($active == 1) {
            $query->where('plans.is_active', 1);
        }

        if ($withUserJoin) {
            $query->leftJoin('user as t2', function ($join) {
                $join->on('plans.dealer_id', '=', 't2.id')
                    ->where('plans.created_by_type', '=', 'dealer');
            });

            $query->leftJoin('supervisor as t3', function ($join) {
                $join->on('plans.created_by_id', '=', 't3.id')
                    ->where('plans.created_by_type', '=', 'admin');
            });
            $query->addSelect([
                DB::raw("CASE
                            WHEN plans.created_by_type = 'dealer' THEN t2.username
                            WHEN plans.created_by_type = 'admin' THEN t3.username
                            ELSE NULL
                         END AS username"),
            ]);
        }

        // Filter by user_id if provided
        if (! empty($user_id)) {
            $query->where('plans.dealer_id', $user_id);
        }
        // Apply search filters if provided
        if (! empty($search)) {
            $query->where(function ($query) use ($search) {
                $query->where('plans.price', 'like', '%' . $search . '%')
                    ->orWhere('plans.currency', 'like', '%' . $search . '%')
                    ->orWhere('plans.created_by_type', 'like', '%' . $search . '%')
                    ->orWhere('plans.points_cnt', 'like', '%' . $search . '%');
            });
        }

        // Add ordering
        $query->orderBy('plans.id', 'desc')
            ->orderBy('plans.points_cnt', 'desc')
            ->orderBy('plans.price', 'desc')
            ->orderBy('plans.currency', 'desc');

        // Return paginated or full results based on $perPage
        if ($perPage) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    public function organizations(): BelongsToMany
    {

        return $this->belongsToMany(Organization::class)
            ->withTimestamps();
    }

    public function connectToAllOrganizationsOld(): void
    {
        if ($this->dealer_id == null) {
            $organizationIds = Organization::pluck('id')->toArray();
            $this->organizations()->sync($organizationIds);
        }
    }

    public function connectToAllOrganizations(): void
    {
        if ($this->dealer_id == null) {
            $organizationIds = Organization::pluck('id')->toArray();

            // Attach organizations with default values for pivot table fields
            $pivotData = [];
            foreach ($organizationIds as $organizationId) {
                $pivotData[$organizationId] = [
                    'points_cnt' => $this->points_cnt,
                    'price'      => $this->price,
                    'currency'   => $this->currency,
                    'is_custom'  => 0,
                    'is_active'  => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $this->organizations()->sync($pivotData);
        }
    }
}
