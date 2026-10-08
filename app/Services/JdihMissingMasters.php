<?php

namespace App\Services;

use App\Models\JdihTarget;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JdihMissingMasters
{
    public function __construct(private JdihReviewDocuments $reviews) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{types: int, categories: int, skipped: int}
     */
    public function create(array $filters): array
    {
        $filters['tab'] = 'masters';
        $review = $this->reviews->listing($filters, JdihTarget::query()->with('sector')->get()->keyBy('source'), 1);
        if ($review['warning'] !== null) {
            throw new RuntimeException($review['warning']);
        }

        return Cache::lock('jdih-create-missing-masters', 120)->block(3, function () use ($review): array {
            return DB::transaction(function () use ($review): array {
                $counts = ['types' => 0, 'categories' => 0, 'skipped' => 0];
                $types = RegulationType::query()->get()->keyBy(fn (RegulationType $type): string => mb_strtolower(trim($type->name)));
                $categories = RegulationCategory::query()->get()->keyBy(fn (RegulationCategory $category): string => $category->sector_id.':'.mb_strtolower(trim($category->name)));
                $sectorIds = Sector::query()->pluck('id')->all();
                foreach ($review['types'] as $slug => $type) {
                    if ($slug === '' || mb_strtolower($slug) === 'needs_review') {
                        continue;
                    }
                    $key = mb_strtolower(trim($type['name']));
                    if (! $types->has($key)) {
                        $types->put($key, RegulationType::create(['name' => $type['name'], 'level' => 4]));
                        $counts['types']++;
                    }
                }
                foreach ($review['categories'] as $category) {
                    if (! in_array($category['sector_id'], $sectorIds, true)) {
                        $counts['skipped']++;

                        continue;
                    }
                    $key = $category['sector_id'].':'.mb_strtolower(trim($category['name']));
                    if (! $categories->has($key)) {
                        $categories->put($key, RegulationCategory::create(['name' => $category['name'], 'sector_id' => $category['sector_id']]));
                        $counts['categories']++;
                    }
                }

                return $counts;
            });
        });
    }
}
