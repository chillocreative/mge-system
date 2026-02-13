<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenseService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'category', 'status', 'date_from', 'date_to', 'search']);
        $expenses = $this->expenseService->listExpenses($filters, $request->integer('per_page', 15));

        return $this->success($expenses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'in:materials,labor,equipment,subcontractor,transport,permits,utilities,office,other'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'max:10240'],
        ]);

        $expense = $this->expenseService->createExpense(
            $validated,
            $request->user()->id,
            $request->file('receipt')
        );

        return $this->created($expense, 'Expense recorded.');
    }

    public function approve(int $id, Request $request): JsonResponse
    {
        try {
            $expense = $this->expenseService->approveExpense($id, $request->user()->id);
            return $this->success($expense, 'Expense approved.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function reject(int $id, Request $request): JsonResponse
    {
        try {
            $expense = $this->expenseService->rejectExpense($id, $request->user()->id);
            return $this->success($expense, 'Expense rejected.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $this->expenseService->deleteExpense($id);

        return $this->success(null, 'Expense deleted.');
    }
}
