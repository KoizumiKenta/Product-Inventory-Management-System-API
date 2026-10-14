<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductFilterRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(ProductFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = $filters['per_page'] ?? 15;

        $version = Cache::get('products:version', 1);
        $key = 'products:'.$version.':'.md5(json_encode($filters));

        $payload = Cache::remember($key, 300, function () use ($filters, $perPage) {
            $products = Product::query()
                ->with(['category', 'suppliers'])
                ->filter($filters)
                ->sort($filters['sort'] ?? null)
                ->paginate($perPage)
                ->withQueryString();

            return ProductResource::collection($products)->response()->getData(true);
        });

        return response()->json($payload);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('suppliers'));

            if ($request->has('suppliers')) {
                $this->syncSuppliers($product, $request->validated('suppliers', []));
            }

            return $product;
        });

        return (new ProductResource($product->load(['category', 'suppliers'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->except('suppliers'));

            if ($request->has('suppliers')) {
                $this->syncSuppliers($product, $request->validated('suppliers', []));
            }
        });

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    public function restore(int $id): ProductResource
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    private function syncSuppliers(Product $product, array $suppliers): void
    {
        $pivot = [];

        foreach ($suppliers as $supplier) {
            $pivot[$supplier['id']] = ['unit_cost' => $supplier['unit_cost'] ?? null];
        }

        $product->suppliers()->sync($pivot);

        // pivot writes don't fire model events, so the observer never sees this
        Cache::add('products:version', 1);
        Cache::increment('products:version');
    }
}
