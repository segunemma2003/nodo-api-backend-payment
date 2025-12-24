<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Get all products for the authenticated business
     */
    public function getProducts(Request $request)
    {
        $business = $this->getBusiness($request);

        $query = $business->products();

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $isActive = filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Search by name or SKU if provided
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'products' => $products,
            'count' => $products->count(),
        ]);
    }

    /**
     * Create a single product
     */
    public function createProduct(Request $request)
    {
        $business = $this->getBusiness($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:255|unique:products,sku,NULL,id,business_id,' . $business->id,
            'price' => 'required|numeric|min:0.01',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $product = Product::create([
            'business_id' => $business->id,
            'name' => $request->name,
            'description' => $request->description,
            'sku' => $request->sku,
            'price' => $request->price,
            'unit_of_measure' => $request->unit_of_measure,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        Log::info('Product created', [
            'product_id' => $product->id,
            'business_id' => $business->id,
            'name' => $product->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }

    /**
     * Create products in bulk
     */
    public function createBulkProducts(Request $request)
    {
        $business = $this->getBusiness($request);

        $request->validate([
            'products' => 'required|array|min:1|max:100', // Limit to 100 products per request
            'products.*.name' => 'required|string|max:255',
            'products.*.description' => 'nullable|string',
            'products.*.sku' => 'nullable|string|max:255',
            'products.*.price' => 'required|numeric|min:0.01',
            'products.*.unit_of_measure' => 'nullable|string|max:50',
            'products.*.is_active' => 'nullable|boolean',
        ]);

        $productsData = [];
        $skus = [];
        $errors = [];

        // Validate SKU uniqueness within the batch and existing products
        foreach ($request->products as $index => $productData) {
            if (!empty($productData['sku'])) {
                // Check for duplicates within the batch
                if (in_array($productData['sku'], $skus)) {
                    $errors[] = "Duplicate SKU '{$productData['sku']}' found in batch (product index: {$index})";
                    continue;
                }

                // Check if SKU already exists for this business
                $existingProduct = Product::where('business_id', $business->id)
                    ->where('sku', $productData['sku'])
                    ->first();

                if ($existingProduct) {
                    $errors[] = "SKU '{$productData['sku']}' already exists (product index: {$index})";
                    continue;
                }

                $skus[] = $productData['sku'];
            }

            $productsData[] = [
                'business_id' => $business->id,
                'name' => $productData['name'],
                'description' => $productData['description'] ?? null,
                'sku' => $productData['sku'] ?? null,
                'price' => $productData['price'],
                'unit_of_measure' => $productData['unit_of_measure'] ?? null,
                'is_active' => isset($productData['is_active']) ? (bool) $productData['is_active'] : true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors found',
                'errors' => $errors,
            ], 422);
        }

        // Bulk insert products
        Product::insert($productsData);

        Log::info('Bulk products created', [
            'business_id' => $business->id,
            'count' => count($productsData),
        ]);

        return response()->json([
            'success' => true,
            'message' => count($productsData) . ' products created successfully',
            'count' => count($productsData),
        ], 201);
    }

    /**
     * Get a single product by ID
     */
    public function getProduct(Request $request, $id)
    {
        $business = $this->getBusiness($request);

        $product = $business->products()->findOrFail($id);

        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }

    /**
     * Update a product
     */
    public function updateProduct(Request $request, $id)
    {
        $business = $this->getBusiness($request);

        $product = $business->products()->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $id . ',id,business_id,' . $business->id,
            'price' => 'sometimes|required|numeric|min:0.01',
            'unit_of_measure' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $product->update($request->only([
            'name',
            'description',
            'sku',
            'price',
            'unit_of_measure',
            'is_active',
        ]));

        Log::info('Product updated', [
            'product_id' => $product->id,
            'business_id' => $business->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => $product->fresh(),
        ]);
    }

    /**
     * Delete a product
     */
    public function deleteProduct(Request $request, $id)
    {
        $business = $this->getBusiness($request);

        $product = $business->products()->findOrFail($id);

        $product->delete();

        Log::info('Product deleted', [
            'product_id' => $product->id,
            'business_id' => $business->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Get business from request
     */
    protected function getBusiness(Request $request): Business
    {
        // Get business from request (set by BusinessAuth middleware)
        $business = $request->get('business') ?? $request->user();
        
        if (!$business instanceof Business) {
            abort(401, 'Unauthenticated');
        }

        return $business;
    }
}
