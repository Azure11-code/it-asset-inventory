<?php

namespace App\Http\Controllers;

use App\Models\Signatory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SignatoryController extends Controller
{
    public function index()
    {
        $signatories = Signatory::orderBy('role')->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('Signatories/Index', [
            'signatories' => $signatories,
            'roles' => [
                ['value' => 'checked_by',  'label' => 'Checked and Issued By'],
                ['value' => 'reviewed_by', 'label' => 'Reviewed By'],
                ['value' => 'approved_by', 'label' => 'Approved By'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Signatory::create($data);

        return back()->with('success', 'Signatory added.');
    }

    public function update(Request $request, Signatory $signatory)
    {
        $data = $this->validated($request);
        $signatory->update($data);

        return back()->with('success', 'Signatory updated.');
    }

    public function destroy(Signatory $signatory)
    {
        $signatory->delete();

        return back()->with('success', 'Signatory removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'title'      => ['nullable', 'string', 'max:255'],
            'role'       => ['required', Rule::in(Signatory::ROLES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99'],
            'is_active'  => ['boolean'],
        ]);
    }
}
