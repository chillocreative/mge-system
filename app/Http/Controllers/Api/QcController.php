<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QcRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class QcController extends Controller
{
    public function index(Request $request)
    {
        $query = QcRecord::with(['project:id,name', 'creator:id,first_name,last_name']);

        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'type' => ['required', 'string', Rule::in(['inspection', 'ncr', 'material_test', 'audit'])],
            'title' => 'required|string|max:255',
            'reference_no' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', 'string', Rule::in(['pending', 'open', 'in_progress', 'resolved', 'closed'])],
            'date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'findings' => 'nullable|string',
            'corrective_action' => 'nullable|string',
            'verified_by' => 'nullable|string|max:255',
            'attachment' => 'nullable|file|extensions:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:1048576',
        ]);

        $data = array_merge($validated, ['created_by' => auth()->id()]);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('qc/attachments', 'public');
        }

        $qcRecord = QcRecord::create($data);

        return response()->json($qcRecord->load(['project:id,name', 'creator:id,first_name,last_name']), 201);
    }

    public function show(int $id)
    {
        $qcRecord = QcRecord::with(['project:id,name', 'creator:id,first_name,last_name'])->findOrFail($id);

        return response()->json($qcRecord);
    }

    public function update(Request $request, int $id)
    {
        $qcRecord = QcRecord::findOrFail($id);

        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'type' => ['required', 'string', Rule::in(['inspection', 'ncr', 'material_test', 'audit'])],
            'title' => 'required|string|max:255',
            'reference_no' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', 'string', Rule::in(['pending', 'open', 'in_progress', 'resolved', 'closed'])],
            'date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'findings' => 'nullable|string',
            'corrective_action' => 'nullable|string',
            'verified_by' => 'nullable|string|max:255',
            'attachment' => 'nullable|file|extensions:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:1048576',
        ]);

        if ($request->hasFile('attachment')) {
            Storage::disk('public')->delete($qcRecord->attachment_path);
            $validated['attachment_path'] = $request->file('attachment')->store('qc/attachments', 'public');
        }

        $qcRecord->update($validated);

        return response()->json($qcRecord->load(['project:id,name', 'creator:id,first_name,last_name']));
    }

    public function destroy(int $id)
    {
        $qcRecord = QcRecord::findOrFail($id);

        if ($qcRecord->attachment_path) {
            Storage::disk('public')->delete($qcRecord->attachment_path);
        }

        $qcRecord->delete();

        return response()->json(null, 204);
    }
}
