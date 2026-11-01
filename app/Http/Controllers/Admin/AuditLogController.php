<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $logs = AuditLog::query()
            ->with('actor:id,name,username')
            ->when($q !== '', fn ($query) => $query->where('action', 'like', addcslashes($q, '\\%_') . '%'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit.index', ['logs' => $logs, 'q' => $q]);
    }
}
