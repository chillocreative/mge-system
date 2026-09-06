<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    // ── Categories ──

    public function categories(): JsonResponse
    {
        return $this->success($this->inventoryService->listCategories());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return $this->created($this->inventoryService->createCategory($validated), 'Category created.');
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return $this->success($this->inventoryService->updateCategory($id, $validated), 'Category updated.');
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $this->inventoryService->deleteCategory($id);

        return $this->success(null, 'Category deleted.');
    }

    // ── Items ──

    public function items(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'status', 'search', 'low_stock']);
        $perPage = min($request->integer('per_page', 15), 100);

        return $this->success($this->inventoryService->listItems($filters, $perPage));
    }

    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:inventory_items,sku'],
            'category_id' => ['nullable', 'exists:inventory_categories,id'],
            'unit' => ['nullable', 'string', 'max:50'],
            'quantity_on_hand' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,discontinued'],
        ]);

        return $this->created($this->inventoryService->createItem($validated, $request->user()->id), 'Item created.');
    }

    public function showItem(int $id): JsonResponse
    {
        return $this->success($this->inventoryService->getItem($id));
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:255', 'unique:inventory_items,sku,'.$id],
            'category_id' => ['nullable', 'exists:inventory_categories,id'],
            'unit' => ['nullable', 'string', 'max:50'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,discontinued'],
        ]);

        return $this->success($this->inventoryService->updateItem($id, $validated), 'Item updated.');
    }

    public function destroyItem(int $id): JsonResponse
    {
        $this->inventoryService->deleteItem($id);

        return $this->success(null, 'Item deleted.');
    }

    // ── Transactions ──

    public function transactions(Request $request, int $itemId): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        return $this->success($this->inventoryService->listTransactions($itemId, $perPage));
    }

    public function storeTransaction(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:in,out,adjustment,write_off'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $transaction = $this->inventoryService->recordTransaction($itemId, $validated, $request->user()->id);

        return $this->created($transaction, 'Transaction recorded.');
    }

    // ── Low stock ──

    public function lowStock(): JsonResponse
    {
        return $this->success($this->inventoryService->lowStock());
    }
}
