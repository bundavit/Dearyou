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
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('administrator', fn ($administrator) => $administrator
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('targetUser', fn ($user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('letter', fn ($letter) => $letter->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->action))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $actions = ModerationAudit::query()
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.audit', compact('actions', 'audits'));
    }
}
