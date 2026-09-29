<?php

namespace App\Http\Controllers;

use App\Http\Requests\JdihTarget\StoreJdihTargetRequest;
use App\Http\Requests\JdihTarget\UpdateJdihTargetRequest;
use App\Models\JdihTarget;
use App\Models\Sector;
use App\Models\UserActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JdihTargetController extends Controller
{
    public function index(): View
    {
        $this->authorizeManagement();

        $targets = JdihTarget::query()->with('sector')->orderBy('name')->get();

        return view('jdih-targets.index', compact('targets'));
    }

    public function create(): View
    {
        $this->authorizeManagement();
        $sectors = Sector::query()->orderBy('name')->get();

        return view('jdih-targets.create', compact('sectors'));
    }

    public function store(StoreJdihTargetRequest $request): RedirectResponse
    {
        $target = JdihTarget::create($request->validated());
        UserActivityLog::log('created', JdihTarget::class, $target->id, "Menambahkan target scraper {$target->name}");

        return redirect()->route('jdih-targets.index')->with('success', 'Target scraper berhasil ditambahkan.');
    }

    public function edit(JdihTarget $jdihTarget): View
    {
        $this->authorizeManagement();
        $sectors = Sector::query()->orderBy('name')->get();

        return view('jdih-targets.edit', ['target' => $jdihTarget, 'sectors' => $sectors]);
    }

    public function update(UpdateJdihTargetRequest $request, JdihTarget $jdihTarget): RedirectResponse
    {
        $jdihTarget->update($request->validated());
        UserActivityLog::log('updated', JdihTarget::class, $jdihTarget->id, "Memperbarui target scraper {$jdihTarget->name}");

        return redirect()->route('jdih-targets.index')->with('success', 'Target scraper berhasil diperbarui.');
    }

    public function destroy(JdihTarget $jdihTarget): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('manage_categories'), 403);

        $name = $jdihTarget->name;
        $jdihTarget->delete();
        UserActivityLog::log('deleted', JdihTarget::class, null, "Menghapus target scraper {$name}");

        return redirect()->route('jdih-targets.index')->with('success', 'Target scraper berhasil dihapus.');
    }

    private function authorizeManagement(): void
    {
        abort_if(auth()->user()->isSubAdmin() && ! auth()->user()->hasPermission('manage_categories'), 403);
    }
}
