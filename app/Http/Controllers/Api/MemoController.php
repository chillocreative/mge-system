<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemoAttachment;
use App\Models\Project;
use App\Services\MemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoController extends Controller
{
    public function __construct(private MemoService $memoService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 20), 100);
        $userId = $request->user()->id;

        $list = $request->query('folder') === 'sent'
            ? $this->memoService->getSent($userId, $perPage)
            : $this->memoService->getInbox($userId, $perPage);

        return $this->success($list);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->memoService->get($id, $request->user()->id));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['count' => $this->memoService->unreadCount($request->user()->id)]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $this->memoService->markAsRead($id, $request->user()->id);

        return $this->success(null, 'Memo marked as read.');
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience' => ['required', 'in:all_users,selected_users,project_members'],
            'user_ids' => ['required_if:audience,selected_users', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'project_id' => ['required_if:audience,project_members', 'exists:projects,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,zip,txt,csv'],
        ]);

        $user = $request->user();

        // Authorize by audience.
        if ($validated['audience'] === 'project_members') {
            $managesProject = Project::where('id', $validated['project_id'])
                ->where('manager_id', $user->id)->exists();
            abort_unless(
                $user->can('memos.send-hr') || ($user->can('memos.send-project') && $managesProject),
                403,
                'You can only memo projects you manage.'
            );
        } else {
            abort_unless($user->can('memos.send-hr'), 403, 'You are not allowed to broadcast memos.');
        }

        $memo = $this->memoService->send($validated, $user, $request->file('attachments', []));

        return $this->created($memo, 'Memo sent.');
    }

    public function downloadAttachment(Request $request, int $id, int $attachmentId)
    {
        $attachment = MemoAttachment::with('memo')->where('memo_id', $id)->findOrFail($attachmentId);
        $memo = $attachment->memo;

        $allowed = $memo->from_user_id === $request->user()->id
            || $memo->recipients()->where('user_id', $request->user()->id)->exists();
        abort_unless($allowed, 403, 'You do not have access to this attachment.');

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }
}
