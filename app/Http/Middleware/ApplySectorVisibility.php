<?php

namespace App\Http\Middleware;

use App\Models\CategoryFile;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationDocument;
use App\Models\RegulationRelatedReference;
use App\Models\Sector;
use App\Models\SubCategory;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySectorVisibility
{
    public function handle(Request $request, Closure $next): Response
    {
        $relations = [
            Sector::class => null,
            RegulationCategory::class => 'sector',
            SubCategory::class => 'category',
            Regulation::class => 'category',
            RegulationDocument::class => 'regulation',
            RegulationRelatedReference::class => 'regulation',
            CategoryFile::class => 'category',
        ];

        foreach ($relations as $model => $relation) {
            new $model;
            $model::addGlobalScope('sector_visibility', function (Builder $query) use ($request, $relation): void {
                $user = $request->user();
                // Admin, subadmin, and reviewer roles can browse both visibility levels.
                // Visitors and ordinary users can only browse public sectors.
                if ($user && ($user->isAdmin() || $user->isSubAdmin() || $user->isReviewer())) {
                    return;
                }

                if ($relation === null) {
                    $query->where('is_public', true);

                    return;
                }

                $query->where(function (Builder $query) use ($relation): void {
                    if (in_array($query->getModel()::class, [RegulationCategory::class, Regulation::class], true)) {
                        $query->whereNull($query->qualifyColumn($relation . '_id'))
                            ->orWhereHas($relation);
                    } else {
                        $query->whereHas($relation);
                    }
                });
            });
        }

        try {
            return $next($request);
        } finally {
            $scopes = Model::getAllGlobalScopes();
            foreach (array_keys($relations) as $model) {
                unset($scopes[$model]['sector_visibility']);
            }
            Model::setAllGlobalScopes($scopes);
        }
    }
}
