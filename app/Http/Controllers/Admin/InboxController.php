<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Response;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $responses = $this->ownedResponses()->with('letter');

        if ($request->status === 'unread') {
            $responses->whereNull('read_at');
        } elseif ($request->status === 'read') {
            $responses->whereNotNull('read_at');
        }

        if ($request->filled('letter')) {
            $responses->where('letter_id', $request->integer('letter'));
        }

        return view('admin.inbox', [
            'responses' => $responses->latest('submitted_at')->paginate(20)->withQueryString(),
            'letters' => auth()->user()->letters()->whereHas('responses')->orderBy('title')->get(['id', 'title']),
            'unreadCount' => $this->ownedResponses()->whereNull('read_at')->count(),
        ]);
    }

    public function show(Response $response)
    {
        $this->own($response);
        $response->update(['read_at' => $response->read_at ?? now()]);

        return view('admin.response', ['response' => $response->load('letter')]);
    }

    public function markUnread(Response $response)
    {
        $this->own($response);
        $response->update(['read_at' => null]);

        return back()->with('success', 'Response marked unread.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'response_ids' => 'required|array|min:1',
            'response_ids.*' => 'integer',
            'action' => 'required|in:read,unread,delete',
        ]);

        $responses = $this->ownedResponses()->whereIn('id', $data['response_ids']);
        $count = (clone $responses)->count();

        match ($data['action']) {
            'read' => $responses->update(['read_at' => now()]),
            'unread' => $responses->update(['read_at' => null]),
            'delete' => $responses->delete(),
        };

        return back()->with('success', "{$count} response(s) updated.");
    }

    public function destroy(Response $response)
    {
        $this->own($response);
        $response->delete();

        return redirect()->route('admin.inbox')->with('success', 'Response deleted.');
    }

    private function ownedResponses()
    {
        return Response::whereHas('letter', fn ($query) => $query->where('user_id', auth()->id()));
    }

    private function own(Response $response): void
    {
        abort_unless($response->letter->user_id === auth()->id(), 403);
    }
}
