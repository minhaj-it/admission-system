<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\StaffManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request, StaffManager $staff): View
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('role')
            ->where('account_type', User::TYPE_STAFF)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . addcslashes($q, '\\%_') . '%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'q' => $q,
            'roles' => $staff->assignableRoles($request->user()),
        ]);
    }

    public function store(UserRequest $request, StaffManager $staff): RedirectResponse
    {
        $staff->create($request->user(), $request->validated());

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function update(UserRequest $request, User $user, StaffManager $staff): RedirectResponse
    {
        Gate::authorize('update', $user);

        $staff->update($request->user(), $user, $request->validated());

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function activate(Request $request, User $user, StaffManager $staff): RedirectResponse
    {
        return $this->setActive($request, $user, $staff, true);
    }

    public function deactivate(Request $request, User $user, StaffManager $staff): RedirectResponse
    {
        return $this->setActive($request, $user, $staff, false);
    }

    private function setActive(Request $request, User $user, StaffManager $staff, bool $active): RedirectResponse
    {
        Gate::authorize('update', $user);

        $staff->setActive($request->user(), $user, $active);

        return redirect()->route('admin.users.index')->with('status', $active ? 'User activated.' : 'User deactivated.');
    }
}
