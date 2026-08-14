<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegulationCategory\StoreRegulationCategoryRequest;
use App\Http\Requests\RegulationCategory\UpdateRegulationCategoryRequest;
use App\Models\CategoryFile;
use App\Models\RegulationCategory;
use App\Models\Sector;
use App\Models\SubCategory;
use App\Models\UserActivityLog;
use App\Repositories\RegulationCategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegulationCategoryController extends Controller
{
    public function __construct(
        private readonly RegulationCategoryRepository $categoryRepository
    ) {}

    public function index(): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        $categories = $this->categoryRepository->all();

        return view('regulation-categories.index', compact('categories'));
    }

    public function create(): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        $sectors = Sector::orderBy('name')->get();

        return view('regulation-categories.create', compact('sectors'));
    }

    public function store(StoreRegulationCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $category = $this->categoryRepository->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sector_id' => $validated['sector_id'] ?? null,
        ]);

        if (! empty($validated['sub_categories'])) {
            $subNames = array_filter($validated['sub_categories'], fn ($name) => ! empty(trim($name)));
            foreach ($subNames as $name) {
                SubCategory::create([
                    'category_id' => $category->id,
                    'name' => trim($name),
                ]);
            }
        }

        UserActivityLog::log('created', RegulationCategory::class, $category->id, "Menambahkan kategori {$category->name}");

        return redirect()->route('regulation-categories.show', $category)
            ->with('success', 'Category berhasil ditambahkan.');
    }

    public function show(RegulationCategory $regulationCategory): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        $regulationCategory->load([
            'files',
            'subCategories',
        ]);

        $regulations = $regulationCategory->regulations()
            ->with([
                'type',
                'documents',
            ])
            ->latest('effective_date')
            ->paginate(10);

        return view('regulation-categories.show', compact(
            'regulationCategory',
            'regulations'
        ));
    }

    public function edit(RegulationCategory $regulationCategory): View
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        $sectors = Sector::orderBy('name')->get();

        return view('regulation-categories.edit', compact('regulationCategory', 'sectors'));
    }

    public function update(UpdateRegulationCategoryRequest $request, RegulationCategory $regulationCategory): RedirectResponse
    {
        $this->categoryRepository->update($regulationCategory, $request->validated());

        UserActivityLog::log('updated', RegulationCategory::class, $regulationCategory->id, "Memperbarui kategori {$regulationCategory->name}");

        return redirect()->route('regulation-categories.show', $regulationCategory)
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(RegulationCategory $regulationCategory): RedirectResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);

        foreach ($regulationCategory->files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }

        $name = $regulationCategory->name;
        $this->categoryRepository->delete($regulationCategory);

        UserActivityLog::log('deleted', RegulationCategory::class, $regulationCategory->id, "Menghapus kategori {$name}");

        return redirect()->route('regulation-categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    public function uploadFile(Request $request, RegulationCategory $regulationCategory): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('manage_categories'), 403);

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'mimes:pdf', 'max:10240'],
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('category-files', 'public');

            CategoryFile::create([
                'category_id' => $regulationCategory->id,
                'filename' => $file->getClientOriginalName(),
                'file_path' => $path,
            ]);
        }

        UserActivityLog::log('uploaded', RegulationCategory::class, $regulationCategory->id, 'Mengunggah '.count($request->file('files'))." file ke kategori {$regulationCategory->name}");

        return redirect()->route('regulation-categories.show', $regulationCategory)
            ->with('success', count($request->file('files')).' file(s) uploaded successfully.');
    }

    public function deleteFile(CategoryFile $file): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_categories'), 403);

        $category = $file->category;

        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        UserActivityLog::log('deleted', CategoryFile::class, $file->id, "Menghapus file {$file->filename} dari kategori {$category->name}");

        return redirect()->route('regulation-categories.show', $category)
            ->with('success', 'File deleted successfully.');
    }

    public function viewFile(CategoryFile $file): StreamedResponse
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);

        return Storage::disk('public')->response($file->file_path, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function storeSubCategory(Request $request, RegulationCategory $regulationCategory): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('manage_sub_categories'), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        SubCategory::create([
            'category_id' => $regulationCategory->id,
            'name' => $request->input('name'),
        ]);

        UserActivityLog::log('created', SubCategory::class, null, "Menambahkan sub kategori {$request->input('name')} ke kategori {$regulationCategory->name}");

        return redirect()->route('regulation-categories.show', $regulationCategory)
            ->with('success', 'Sub category berhasil ditambahkan.');
    }

    public function updateSubCategory(Request $request, SubCategory $subCategory): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('manage_sub_categories'), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $subCategory->update(['name' => $request->input('name')]);

        UserActivityLog::log('updated', SubCategory::class, $subCategory->id, "Memperbarui sub kategori {$subCategory->name}");

        return redirect()->route('regulation-categories.show', $subCategory->category)
            ->with('success', 'Sub category berhasil diperbarui.');
    }

    public function toggleSubCategory(SubCategory $subCategory): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_sub_categories'), 403);

        $subCategory->update(['is_active' => ! $subCategory->is_active]);

        UserActivityLog::log('toggled', SubCategory::class, $subCategory->id, ($subCategory->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." sub kategori {$subCategory->name}");

        return redirect()->back()
            ->with('success', 'Status sub category berhasil diperbarui.');
    }

    public function destroySubCategory(SubCategory $subCategory): RedirectResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);

        $category = $subCategory->category;
        $name = $subCategory->name;
        $subCategory->delete();

        UserActivityLog::log('deleted', SubCategory::class, null, "Menghapus sub kategori {$name} dari kategori {$category->name}");

        return redirect()->route('regulation-categories.show', $category)
            ->with('success', 'Sub category berhasil dihapus.');
    }
}
