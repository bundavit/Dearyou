<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAudit;
use Illuminate\Http\Request;

class ModerationAuditController extends Controller
{
    public function __invoke(Request $request)
    {
        $audits = ModerationAudit::query()
            ->with(['administrator', 'targetUser', 'letter'])
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->action))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit', compact('audits'));
    }
}
