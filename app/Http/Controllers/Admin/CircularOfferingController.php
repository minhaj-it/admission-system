<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfferingRequest;
use App\Models\AdmissionCircular;
use App\Services\OfferingService;
use Illuminate\Http\RedirectResponse;

class CircularOfferingController extends Controller
{
    public function store(OfferingRequest $request, AdmissionCircular $circular, OfferingService $service): RedirectResponse
    {
        abort_if($circular->status === 'archived', 403);

        $result = $service->setup($circular, $request->validated());

        $parts = [];
        if ($result['created']) {
            $parts[] = count($result['created']) . ' class setup(s) created';
        }
        if ($result['updated']) {
            $parts[] = count($result['updated']) . ' updated';
        }

        return redirect()->route('admin.circulars.show', $circular)->with('status', ucfirst(implode(', ', $parts)) . '.');
    }
}
