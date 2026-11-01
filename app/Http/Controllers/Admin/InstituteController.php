<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InstituteRequest;
use App\Models\Institution;
use App\Services\InstituteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InstituteController extends Controller
{
    public function edit(): View
    {
        return view('admin.institute.edit', ['institute' => Institution::current(), 'fileTypes' => InstituteRequest::FILE_TYPES]);
    }

    public function update(InstituteRequest $request, InstituteService $service): RedirectResponse
    {
        $service->update($request->validated(), $request->file('logo'), $request->boolean('remove_logo'));

        return redirect()->route('admin.institute.edit')->with('status', 'Institute profile saved.');
    }
}
