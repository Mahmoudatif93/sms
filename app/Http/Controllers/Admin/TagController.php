<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SmsApiController;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Models\Tag;
use App\Models\OrganizationTag;
class TagController extends SmsApiController implements HasMiddleware
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
        $query = Tag::with('parent');
        if (!empty($request->search)) {
            $query->where('name_ar', 'like', '%' . $request->search . '%')
                ->orWhere('name_en', 'like', '%' . $request->search . '%');
        } else {
            $query->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('parent_id')
                ->orderBy('name_en');
        }
        $tags = $query->paginate($perPage, ['*'], 'page', $page);
        $response = $tags->getCollection()->map(function ($tag) {
            return new \App\Http\Responses\Tag($tag);
        });
        $tags->setCollection($response);
        return $this->paginateResponse(true, 'tags retrieved successfully', $tags);
    }

    public function getParentTagsWithChildren(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Start with parent tags (where parent_id is null)
        $query = Tag::with('parent')
            ->whereNull('parent_id');

        if (!empty($request->search)) {
            // For search, we want to include both parents and children
            $query = Tag::with('parent')
                ->where(function ($q) use ($request) {
                    $q->where('name_ar', 'like', '%' . $request->search . '%')
                        ->orWhere('name_en', 'like', '%' . $request->search . '%');
                });
        }

        // Order by name_en or name_ar (you can make this configurable)
        $query->orderBy('name_en');

        $tags = $query->paginate($perPage, ['*'], 'page', $page);

        // If not searching, get children for each parent
        if (empty($request->search)) {
            $response = $tags->getCollection()->map(function ($tag) {
                $data = new \App\Http\Responses\Tag($tag);
                // Get children and sort them
                $children = Tag::where('parent_id', $tag->id)
                    ->orderBy('name_en')
                    ->get()
                    ->map(function ($child) {
                        return new \App\Http\Responses\Tag($child);
                    });
                // Add children to the response
                $data->children = $children;
                return $data;
            });
        } else {
            // For search results, just map the tags normally
            $response = $tags->getCollection()->map(function ($tag) {
                return new \App\Http\Responses\Tag($tag);
            });
        }

        $tags->setCollection($response);
        return $this->paginateResponse(true, 'tags retrieved successfully', $tags);
    }

    public function getChildren(Request $request, Tag $tag)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $query = $tag->children();

            // Add search functionality if needed
            if (!empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('name_ar', 'like', '%' . $request->search . '%')
                        ->orWhere('name_en', 'like', '%' . $request->search . '%');
                });
            }

            // Order by name
            $query->orderBy('name_en');

            $children = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform the data using the Tag Response
            $response = $children->getCollection()->map(function ($child) {
                return new \App\Http\Responses\Tag($child);
            });

            $children->setCollection($response);

            return $this->paginateResponse(
                true,
                'Children tags retrieved successfully',
                $children
            );
        } catch (\Exception $e) {
            return $this->response(false, 'Failed to retrieve children tags: ' . $e->getMessage());
        }
    }
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name_ar' => 'required|string|max:255',
                'name_en' => 'required|string|max:255',
                'parent_id' => 'nullable|exists:tag,id'
            ]);

            $tag = Tag::create($validated);

            return $this->response(true, 'Tag created successfully', new \App\Http\Responses\Tag($tag));
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage());
        }
    }

    public function show(Tag $tag)
    {
        return $this->response(true, "tag", new \App\Http\Responses\Tag($tag));
    }

    public function update(Request $request, Tag $tag)
    {
        try {
            $validated = $request->validate([
                'name_ar' => 'required|string|max:255',
                'name_en' => 'required|string|max:255',
                'parent_id' => 'nullable|exists:tag,id'
            ]);

            $tag->update($validated);

            return $this->response(true, 'Tag updated successfully', new \App\Http\Responses\Tag($tag));
        } catch (\Exception $e) {
            return $this->response(false, 'Failed to update tag: ' . $e->getMessage());
        }
    }

    public function destroy(Tag $tag)
    {
        try {
            $tag->delete();
            return $this->response(true, 'Tag deleted successfully');
        } catch (\Exception $e) {
            return $this->response(false, 'Failed to delete tag: ' . $e->getMessage());
        }
    }

    public function getOrganizations(Request $request, Tag $tag)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
    
            $query = OrganizationTag::where('tag_id', $tag->id)
                ->with(['organization' => function($query) {
                    $query->select('id', 'name');  // Add any other organization fields you need
                }]);
    
            // Add search functionality if needed
            if (!empty($request->search)) {
                $query->whereHas('organization', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
            }
    
            // Add sorting if needed
            $query->orderBy('created_at', 'desc');
    
            $organizationTags = $query->paginate($perPage, ['*'], 'page', $page);
           
            $response = $organizationTags->getCollection()->map(function ($OrganizationTag) {
                return new \App\Http\Responses\OrganizationTag($OrganizationTag);
            });
            $organizationTags->setCollection($response);
            return $this->paginateResponse(true, 'organization tags retrieved successfully', $organizationTags);

          
        } catch (\Exception $e) {
            return $this->response(false, 'Failed to retrieve organization tags: ' . $e->getMessage());
        }
    }

    public function attachOrganization(Request $request, Tag $tag,Organization $organization)
    {
        try {

            $tag->organizations()->attach($organization->id);
            return $this->response(
                true,
                'Organization attached successfully to tag',
                new \App\Http\Responses\Tag($tag->load('organizations'))
            );
        } catch (\Exception $e) {
            return $this->response(false, 'Failed to attach organization: ' . $e->getMessage());
        }
    }

    public function detachOrganization(Request $request, Tag $tag,Organization $organization)
    {
        try {
      

            $tag->organizations()->detach($organization->id);

            return $this->response(
                true,
                'Organization detached successfully from tag',
                new \App\Http\Responses\Tag($tag->load('organizations'))
            );
        } catch (\Exception $e) {
            return $this->response(false, 'Failed to detach organization: ' . $e->getMessage());
        }
    }
}
