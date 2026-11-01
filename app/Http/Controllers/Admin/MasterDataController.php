<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterItemRequest;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Support\MasterData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function index(string $type = 'class'): View
    {
        $config = MasterData::config($type);

        $counts = [];
        foreach (MasterData::TYPES as $key => $definition) {
            $counts[$key] = $definition['model']::query()->count();
        }

        return view('admin.master.index', [
            'type' => $type,
            'config' => $config,
            'items' => $config['model']::query()->ordered()->get(),
            'types' => MasterData::TYPES,
            'counts' => $counts,
        ]);
    }

    public function store(MasterItemRequest $request, string $type): RedirectResponse
    {
        $config = MasterData::config($type);
        $model = $config['model'];

        $item = new $model(['name' => $request->validated('name'), 'is_active' => true]);
        $item->sort_order = ((int) $model::query()->max('sort_order')) + 1;

        if ($type === 'class') {
            $item->requires_group = $request->boolean('requires_group');
        }

        if ($type === 'session') {
            $item->institution_id = Institution::current()->id;
        }

        if ($type === 'document') {
            $item->kind = $request->validated('kind');
            $item->step = (int) $request->validated('step');
            $item->key = $this->uniqueKey($item->name);
        }

        $item->save();

        AuditLog::record('master.created', $item, null, ['list' => $type, 'name' => $item->name]);

        return redirect()->route('admin.master.index', $type)->with('status', $config['label'] . ' added.');
    }

    public function update(MasterItemRequest $request, string $type, int $id): RedirectResponse
    {
        $config = MasterData::config($type);
        $item = $config['model']::query()->findOrFail($id);

        $old = ['name' => $item->name];
        $item->name = $request->validated('name');

        if ($type === 'class') {
            $old['requires_group'] = $item->requires_group;
            $item->requires_group = $request->boolean('requires_group');
        }

        if ($type === 'document') {
            $old['kind'] = $item->kind;
            $old['step'] = $item->step;
            $item->kind = $request->validated('kind');
            $item->step = (int) $request->validated('step');
        }

        $item->save();

        $new = ['name' => $item->name] + ($type === 'class' ? ['requires_group' => $item->requires_group] : []) + ($type === 'document' ? ['kind' => $item->kind, 'step' => $item->step] : []);

        if ($old !== $new) {
            AuditLog::record('master.updated', $item, ['list' => $type] + $old, ['list' => $type] + $new);
        }

        return redirect()->route('admin.master.index', $type)->with('status', $config['label'] . ' updated.');
    }

    public function toggle(string $type, int $id): RedirectResponse
    {
        $config = MasterData::config($type);
        $item = $config['model']::query()->findOrFail($id);

        $item->is_active = ! $item->is_active;
        $item->save();

        AuditLog::record($item->is_active ? 'master.activated' : 'master.deactivated', $item, null, ['list' => $type, 'name' => $item->name]);

        return redirect()->route('admin.master.index', $type)->with('status', $item->is_active ? 'Activated.' : 'Deactivated.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $config = MasterData::config($type);
        $item = $config['model']::query()->findOrFail($id);

        if ($item->usageCount() > 0) {
            throw ValidationException::withMessages(['item' => "\"{$item->name}\" is used by circular setups and cannot be deleted. Deactivate it instead."]);
        }

        AuditLog::record('master.deleted', $item, ['list' => $type, 'name' => $item->name], null);
        $item->delete();

        return redirect()->route('admin.master.index', $type)->with('status', 'Deleted.');
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_') ?: 'document', 34, '');
        $key = $base;
        $i = 2;

        while (\App\Models\DocumentType::where('key', $key)->exists()) {
            $key = $base . '_' . $i++;
        }

        return $key;
    }
}
