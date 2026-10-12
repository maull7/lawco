<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegalDecision\IndexLegalDecisionRequest;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class LegalDecisionController extends Controller
{
    public function index(IndexLegalDecisionRequest $request): View
    {
        $filters = $request->validated();
        $search = trim($filters['search'] ?? '');
        $hasFilters = $search !== '' || isset($filters['type_id']) || isset($filters['category_id']) || isset($filters['year']);
        $query = Regulation::query()->with(['type', 'category']);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('regulation_number', 'like', "%{$search}%");
            });
        }

        foreach (['type_id' => 'regulation_type_id', 'category_id' => 'category_id', 'year' => 'year'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        return view('legal-decisions.index', [
            'filters' => $filters,
            'regulations' => $hasFilters ? $query->select(['id', 'title', 'regulation_number', 'regulation_type_id', 'category_id', 'year', 'file_path'])->orderByDesc('id')->paginate(15)->withQueryString() : null,
            'types' => RegulationType::query()->orderBy('name')->get(['id', 'name']),
            'categories' => RegulationCategory::query()->orderBy('name')->get(['id', 'name']),
            'years' => Regulation::query()->select('year')->distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }
}
