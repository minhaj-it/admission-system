<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionsRequest;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use App\Services\RoleManager;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::withCount('users')->orderBy('id')->get();
        $selected = $roles->firstWhere('id', (int) $request->query('role')) ?? $roles->first();

        return view('admin.roles.index', [
            'roles' => $roles,
            'selected' => $selected,
            'granted' => $selected ? array_flip($selected->permissionKeys()) : [],
            'tab' => $request->query('tab') === 'access' ? 'access' : 'type',
            'modules' => Permissions::modules(),
            'special' => Permissions::special(),
        ]);
    }

    public function store(RoleRequest $request, RoleManager $roles): RedirectResponse
    {
        $roles->create($request->validated());

        return redirect()->route('admin.roles.index')->with('status', 'User type created.');
    }

    public function update(RoleRequest $request, Role $role, RoleManager $roles): RedirectResponse
    {
        Gate::authorize('update', $role);

        $roles->update($role, $request->validated());

        return redirect()->route('admin.roles.index')->with('status', 'User type updated.');
    }

    public function permissions(PermissionsRequest $request, Role $role, RoleManager $roles): RedirectResponse
    {
        Gate::authorize('permissions', $role);

        $roles->syncPermissions($request->user(), $role, $request->validated('permissions', []));

        return redirect()->route('admin.roles.index', ['role' => $role->id, 'tab' => 'access'])->with('status', 'Access saved.');
    }

    public function destroy(Role $role, RoleManager $roles): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $roles->delete($role);

        return redirect()->route('admin.roles.index')->with('status', 'User type deleted.');
    }
}
