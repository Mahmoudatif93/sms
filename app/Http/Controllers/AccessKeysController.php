<?php

namespace App\Http\Controllers;

use App\Models\AccessKey;
use Illuminate\Http\Request;
use Random\RandomException;
use Str;

class AccessKeysController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/organizations/{organizationId}/access-keys",
     *     tags={"Access Keys"},
     *     summary="List access keys for an organization",
     *     description="Retrieve the list of access keys that belong to an organization",
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     ),
     *     security={{"apiAuth": {}}}
     * )
     */
    public function index(Request $request, $organizationId)
    {
        $keys = AccessKey::where('organization_id', $organizationId)->with('roles')->paginate($request->get('per_page', 15));

        return $this->paginateResponse(true, '', $keys);
    }

    /**
     * @OA\Post(
     *     path="/organizations/{organizationId}/access-keys",
     *     tags={"Access Keys"},
     *     summary="Create a new access key",
     *     description="Create a new access key for the organization",
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "description"},
     *             @OA\Property(property="name", type="string", example="Access Key Name"),
     *             @OA\Property(property="description", type="string", example="Access Key Description"),
     *             @OA\Property(property="roleRefs", type="array", @OA\Items(type="string", format="uuid"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created"
     *     ),
     *     security={{"apiAuth": {}}}
     * )
     * @throws RandomException
     */
    public function store(Request $request, $organizationId)
    {
        $formatted = AccessKey::generateFormattedToken();

        $key = AccessKey::create([
            'id' => Str::uuid(),
            'organization_id' => $organizationId,
            'name' => $request->name,
            'description' => $request->description,
            'suffix' => $formatted['suffix'],
            'token' => $formatted['secret'], // 👈 storing as-is (plaintext)
            'type' => 'user',
        ]);

        // Attach roles
        if ($request->has('roleRefs')) {
            foreach ($request->roleRefs as $roleRef) {
                $key->roles()->attach($roleRef, ['type' => 'user']); // Specify the type
            }
        }

        // Override for response only
        $key->token = $formatted['token'];

        return response()->json($key, 201);
    }


    /**
     * @OA\post(
     *     path="/api/organizations/{organizationId}/access-keys/{id}",
     *     summary="Update an access key",
     *     tags={"Access Keys"},
     *     description="Update an existing access key",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Access Key ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "description"},
     *             @OA\Property(property="name", type="string", example="Updated Access Key Name"),
     *             @OA\Property(property="description", type="string", example="Updated Access Key Description"),
     *             @OA\Property(property="roleRefs", type="array", @OA\Items(type="string", format="uuid"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updated"
     *     ),
     *     security={{"apiAuth": {}}}
     * )
     * @throws RandomException
     */
    public function update(Request $request, $organizationId, $id)
    {
        $key = AccessKey::where('id', $id)->first();
        if (!$key) {
            return response()->json(['message' => 'Access key not found'], 404);
        }
        $key->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Sync roles
        if ($request->has('roleRefs')) {
            $roleData = array_map(function ($roleRef) {
                return ['type' => 'user']; // Specify the type for each roleRef
            }, $request->roleRefs);

            $key->roles()->sync(array_combine($request->roleRefs, $roleData)); // Combine roleRefs with their type
        }

        return response()->json($key, 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/organizations/{organizationId}/access-keys/{id}",
     *     summary="Delete an access key",
     *     tags={"Access Keys"},
     *     description="Delete an existing access key",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Access Key ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted"
     *     ),
     *     security={{"apiAuth": {}}}
     * )
     * @throws RandomException
     */
    public function destroy(Request $request, $organizationId, $id)
    {
        $key = AccessKey::findOrFail($id);
        $key->delete();
        $key->roles()->detach();

        return response()->json(null, 200);
    }
}
