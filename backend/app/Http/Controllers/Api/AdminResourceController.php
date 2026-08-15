<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Resource;
use App\Services\ResourceStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminResourceController extends Controller
{
    private const TYPES = ['n8n Workflow', 'Spreadsheet', 'CSV', 'Template', 'ZIP / Project', 'PDF', 'Document', 'Code / Example', 'Image', 'Other'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'resource_type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'access_type' => ['nullable', 'in:public,purchase_required'],
        ]);

        $resources = Resource::with('products:id,name,slug')
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")))
            ->when($filters['product_id'] ?? null, fn ($query, $id) => $query->whereHas('products', fn ($product) => $product->where('products.id', $id)))
            ->when($filters['resource_type'] ?? null, fn ($query, $type) => $query->where('resource_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['access_type'] ?? null, fn ($query, $access) => $query->where('access_type', $access))
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $resources]);
    }

    public function show(Resource $resource)
    {
        return response()->json(['data' => $resource->load('products:id,name,slug', 'versions.creator:id,name,email')]);
    }

    public function store(Request $request, ResourceStorageService $storage)
    {
        $data = $this->data($request);
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids'], $data['file']);

        return DB::transaction(function () use ($request, $storage, $data, $productIds) {
            $resource = Resource::create($data + [
                'created_by' => $request->user()->id,
                'storage_disk' => 'private',
            ]);

            if ($resource->source_type === 'uploaded_file') {
                $fileData = $storage->store($resource, $request->file('file'), $resource->version, $request->user());
                $resource->update($fileData);
            }

            $resource->products()->sync($productIds);

            return response()->json(['data' => $resource->fresh()->load('products:id,name,slug', 'versions')], 201);
        });
    }

    public function update(Request $request, Resource $resource, ResourceStorageService $storage)
    {
        $data = $this->data($request, $resource);
        $productIds = $data['product_ids'] ?? null;
        unset($data['product_ids'], $data['file']);

        return DB::transaction(function () use ($request, $resource, $storage, $data, $productIds) {
            $resource->fill($data);

            if (($data['source_type'] ?? $resource->source_type) === 'external_url') {
                $resource->fill([
                    'storage_path' => null,
                    'original_filename' => null,
                    'mime_type' => null,
                    'file_size' => null,
                ]);
            }

            $resource->save();

            if ($request->hasFile('file')) {
                $fileData = $storage->store($resource, $request->file('file'), $resource->version, $request->user());
                $resource->update($fileData + ['source_type' => 'uploaded_file']);
            }

            if (is_array($productIds)) {
                $resource->products()->sync($productIds);
            }

            return response()->json(['data' => $resource->fresh()->load('products:id,name,slug', 'versions')]);
        });
    }

    public function archive(Resource $resource)
    {
        $resource->update(['status' => 'archived']);

        return response()->json(['data' => $resource->fresh()->load('products:id,name,slug')]);
    }

    public function attach(Request $request, Product $product)
    {
        $data = $request->validate(['resource_id' => ['required', 'integer', 'exists:resources,id']]);
        $product->resources()->syncWithoutDetaching([$data['resource_id']]);

        return response()->json(['data' => $product->fresh('resources')]);
    }

    public function detach(Product $product, Resource $resource)
    {
        $product->resources()->detach($resource->id);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function data(Request $request, ?Resource $resource = null): array
    {
        $sourceType = $request->input('source_type', $resource?->source_type ?: 'uploaded_file');

        $data = $request->validate([
            'title' => [$resource ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => [$resource ? 'sometimes' : 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('resources', 'slug')->ignore($resource)],
            'description' => ['nullable', 'string', 'max:5000'],
            'resource_type' => [$resource ? 'sometimes' : 'required', Rule::in(self::TYPES)],
            'source_type' => ['nullable', 'in:uploaded_file,external_url'],
            'external_url' => [$sourceType === 'external_url' ? 'required' : 'nullable', 'url', 'max:2048'],
            'version' => ['nullable', 'string', 'max:50'],
            'access_type' => ['nullable', 'in:public,purchase_required'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'file' => [$resource || $sourceType === 'external_url' ? 'nullable' : 'required', 'file'],
        ]);

        $data['source_type'] ??= $resource?->source_type ?: 'uploaded_file';
        $data['version'] ??= $resource?->version ?: '1.0';
        $data['access_type'] ??= $resource?->access_type ?: 'public';
        $data['status'] ??= $resource?->status ?: 'draft';

        if ($data['source_type'] === 'uploaded_file') {
            $data['external_url'] = null;
        }

        return $data;
    }
}
