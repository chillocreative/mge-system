<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseService
{
    public function listExpenses(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Expense::with([
            'project:id,name,code',
            'submitter:id,first_name,last_name',
            'approver:id,first_name,last_name',
        ]);

        if (!empty($filters['project_id'])) {
            $query->forProject((int) $filters['project_id']);
        }
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->forPeriod($filters['date_from'], $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $query->where('title', 'like', "%{$filters['search']}%");
        }

        return $query->latest('expense_date')->paginate($perPage);
    }

    public function createExpense(array $data, int $submittedBy, ?UploadedFile $receipt = null): Expense
    {
        $expenseData = [
            'project_id' => $data['project_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'other',
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'vendor' => $data['vendor'] ?? null,
            'submitted_by' => $submittedBy,
        ];

        if ($receipt) {
            $expenseData['receipt_path'] = $receipt->store('expenses/receipts', 'public');
        }

        return Expense::create($expenseData)->load([
            'project:id,name,code',
            'submitter:id,first_name,last_name',
        ]);
    }

    public function approveExpense(int $id, int $approvedBy): Expense
    {
        $expense = Expense::findOrFail($id);

        if ($expense->status !== 'pending') {
            throw new \InvalidArgumentException("Only pending expenses can be approved. Current status: {$expense->status}");
        }

        $expense->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        // Update project spent amount
        if ($expense->project_id) {
            $totalApproved = Expense::forProject($expense->project_id)
                ->byStatus('approved')
                ->sum('amount');
            $expense->project->update(['spent' => $totalApproved]);
        }

        return $expense->fresh(['project:id,name,code', 'submitter:id,first_name,last_name', 'approver:id,first_name,last_name']);
    }

    public function rejectExpense(int $id, int $rejectedBy): Expense
    {
        $expense = Expense::findOrFail($id);

        if ($expense->status !== 'pending') {
            throw new \InvalidArgumentException("Only pending expenses can be rejected. Current status: {$expense->status}");
        }

        $expense->update([
            'status' => 'rejected',
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
        ]);

        return $expense->fresh();
    }

    public function deleteExpense(int $id): bool
    {
        return Expense::findOrFail($id)->delete();
    }
}
