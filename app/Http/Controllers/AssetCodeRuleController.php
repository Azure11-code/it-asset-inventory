<?php

namespace App\Http\Controllers;

use App\Models\AssetCodeRule;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssetCodeRuleController extends Controller
{
    public function index()
    {
        $rules = AssetCodeRule::with('category:id,name')
            ->orderBy('sort_order')->orderBy('prefix_start')
            ->get()
            ->map(fn ($r) => [
                'id'           => $r->id,
                'label'        => $r->label,
                'prefix_start' => $r->prefix_start,
                'prefix_end'   => $r->prefix_end,
                'range'        => $r->prefix_start . '–' . $r->prefix_end,
                'category_id'  => $r->category_id,
                'category'     => $r->category ? ['id' => $r->category->id, 'name' => $r->category->name] : null,
                'is_active'    => $r->is_active,
                'sort_order'   => $r->sort_order,
                'next_preview' => $r->nextTag()['formatted'] ?? 'RANGE FULL',
            ]);

        return Inertia::render('AssetCodeRules/Index', [
            'rules'      => $rules,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        AssetCodeRule::create($data);
        return redirect()->route('asset-code-rules.index')->with('success', 'Rule created.');
    }

    public function update(Request $request, AssetCodeRule $rule)
    {
        $data = $this->validateData($request);
        $rule->update($data);
        return redirect()->route('asset-code-rules.index')->with('success', 'Rule updated.');
    }

    public function destroy(AssetCodeRule $rule)
    {
        $rule->delete();
        return redirect()->route('asset-code-rules.index')->with('success', 'Rule removed.');
    }

    /** Preview endpoint used by the Asset Create form: returns next tag for a rule. */
    public function next(AssetCodeRule $rule, Request $request)
    {
        $year = $request->query('year') ? (int) $request->query('year') : null;
        $next = $rule->nextTag($year);
        return response()->json([
            'rule_id'   => $rule->id,
            'year'      => $next['year'] ?? (int) date('Y'),
            'number'    => $next['number'] ?? null,
            'formatted' => $next['formatted'] ?? null,
            'full'      => $next === null,
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'label'        => ['required', 'string', 'max:100'],
            'prefix_start' => ['required', 'integer', 'min:0', 'max:99999'],
            'prefix_end'   => ['required', 'integer', 'min:0', 'max:99999', 'gte:prefix_start'],
            'category_id'  => ['nullable', 'exists:categories,id'],
            'is_active'    => ['boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
