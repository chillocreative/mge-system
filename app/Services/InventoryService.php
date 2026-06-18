<?php

namespace App\Services;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    // ── Categories ──

    public function listCategories(): Collection
    {
        return InventoryCategory::withCount('items')->orderBy('name')->get();
    }

    public function createCategory(array $data): InventoryCategory
    {
        return InventoryCategory::create($data);
    }

    public function updateCategory(int $id, array $data): InventoryCategory
    {
        $category = InventoryCategory::findOrFail($id);
        $category->update($data);
        return $category;
    }

    public function deleteCategory(int $id): void
    {
        InventoryCategory::findOrFail($id)->delete();
    }

    // ── Items ──

    public function listItems(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = InventoryItem::with('category:id,name')->orderBy('name');

        if (!empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['search'])) $query->search($filters['search']);
        if (!empty($filters['low_stock'])) $query->lowStock();

        return $query->paginate($perPage);
    }

    public function createItem(array $data, int $userId): InventoryItem
    {
        $data['created_by'] = $userId;
        $item = InventoryItem::create($data);
        return $item->load('category:id,name');
    }

    public function getItem(int $id): InventoryItem
    {
        return InventoryItem::with([
            'category:id,name',
            'creator:id,first_name,last_name',
        ])->findOrFail($id);
    }

    public function updateItem(int $id, array $data): InventoryItem
    {
        $item = InventoryItem::findOrFail($id);
        // quantity_on_hand is managed through transactions, never directly editable here
        unset($data['quantity_on_hand']);
        $item->update($data);
        return $item->load('category:id,name');
    }

    public function deleteItem(int $id): void
    {
        InventoryItem::findOrFail($id)->delete();
    }

    // ── Transactions ──

    public function listTransactions(int $itemId, int $perPage = 15): LengthAwarePaginator
    {
        return InventoryTransaction::where('item_id', $itemId)
            ->with('creator:id,first_name,last_name')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function recordTransaction(int $itemId, array $data, int $userId): InventoryTransaction
    {
        return DB::transaction(function () use ($itemId, $data, $userId) {
            $item = InventoryItem::lockForUpdate()->findOrFail($itemId);

            $qty = (float) $data['quantity'];

            $item->quantity_on_hand = match ($data['type']) {
                'in' => $item->quantity_on_hand + $qty,
                'out', 'write_off' => $item->quantity_on_hand - $qty,
                'adjustment' => $qty, // set to the provided value
                default => $item->quantity_on_hand,
            };
            $item->save();

            $data['item_id'] = $item->id;
            $data['created_by'] = $userId;

            return InventoryTransaction::create($data)->load('creator:id,first_name,last_name');
        });
    }

    // ── Low stock ──

    public function lowStock(): Collection
    {
        return InventoryItem::with('category:id,name')
            ->lowStock()
            ->byStatus('active')
            ->orderBy('name')
            ->get();
    }
}
