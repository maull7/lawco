<?php

use App\Http\Controllers\AiPreviewController;
use App\Http\Controllers\AiPromptController;
use App\Http\Controllers\AiSummaryController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentPartitionController;
use App\Http\Controllers\LegalCaseController;
use App\Http\Controllers\LegalNecessityController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PackagePaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegulationCategoryController;
use App\Http\Controllers\RegulationChatController;
use App\Http\Controllers\RegulationController;
use App\Http\Controllers\RegulationTypeController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewDocumentController;
use App\Http\Controllers\ReviewReportController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\TypePromptController;
use App\Http\Controllers\User\RegulationCategoryUserController;
use App\Http\Controllers\User\RegulationTypeUserController;
use App\Http\Controllers\User\SubCategoryUserController;
use App\Http\Controllers\UserController;
use App\Models\Package;
use App\Models\Regulation;
use App\Models\ReviewDocument;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $packages = Package::where('is_active', true)->orderBy('sort')->orderBy('id')->get();

    return view('index', compact('packages'));
})->name('index');

Route::get('/sitemap.xml', function () {
    return response()->view('sitemap', [
        'urls' => [
            ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
        ],
    ])->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/index-dashboard', [DashboardController::class, 'landing'])->name('index-dash');

Route::post('/legal-necessities', [LegalNecessityController::class, 'store'])
    ->name('legal-necessities.store')
    ->middleware('throttle:5,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
Route::post('/email/resend', [EmailVerificationController::class, 'send'])->name('verification.send')->middleware('throttle:6,1');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
});

Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('role:admin')->group(function () {
        Route::get('/paket/pembayaran/konfirmasi', [PackagePaymentController::class, 'confirmations'])->name('confirm.packages.payment.confirmations');
        Route::post('/paket/pembayaran/konfirmasi/{userPackage}', [PackagePaymentController::class, 'confirm'])->name('packages.payment.confirm');
    });

    Route::get('/paket/pembayaran/{userPackage}', [PackagePaymentController::class, 'show'])->name('packages.payment');
    Route::post('/paket/pembayaran/{userPackage}/bukti', [PackagePaymentController::class, 'submitProof'])->name('packages.payment.submit');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/compliance-monitoring', [DashboardController::class, 'compliance'])->name('compliance.monitoring');

    Route::get('/konsultasi-kak-vesta', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::post('/konsultasi-kak-vesta', [ConsultationController::class, 'store'])->name('consultations.store');
    Route::get('/konsultasi-kak-vesta/{session}', [ConsultationController::class, 'show'])->name('consultations.show');
    Route::post('/konsultasi-kak-vesta/{session}/chat', [ConsultationController::class, 'ask'])->name('consultations.chat.ask')->middleware('throttle:10,1');
    Route::post('/konsultasi-kak-vesta/{session}/regulations', [ConsultationController::class, 'addRegulations'])->name('consultations.regulations.add');

    Route::prefix('user')->group(function () {
        Route::get('/regulation-categories', [RegulationCategoryUserController::class, 'index'])->name('user.regulation-categories.index');
        Route::get('/regulation-categories/{regulationCategory}', [RegulationCategoryUserController::class, 'show'])->name('user.regulation-categories.show');

        Route::get('/sub-categories', [SubCategoryUserController::class, 'index'])->name('user.sub-categories.index');
        Route::get('/sub-categories/{subCategory}', [SubCategoryUserController::class, 'show'])->name('user.sub-categories.show');

        Route::get('/regulation-types', [RegulationTypeUserController::class, 'index'])->name('user.regulation-types.index');
        Route::get('/regulation-types/{regulationType}', [RegulationTypeUserController::class, 'show'])->name('user.regulation-types.show');
    });

    Route::resource('review-documents', ReviewDocumentController::class);
    Route::post('/review-documents/{reviewDocument}/submit', [ReviewDocumentController::class, 'submit'])->name('review-documents.submit');
    Route::get('/review-documents/{reviewDocument}/file', [ReviewDocumentController::class, 'viewFile'])->name('review-documents.view-file');
    Route::get('/review-documents/{reviewDocument}/viewer', [ReviewDocumentController::class, 'viewer'])->name('review-documents.viewer');

    Route::get('/reviews/{reviewDocument}/create', [ReviewController::class, 'create'])->name('reviews.create');
    Route::resource('reviews', ReviewController::class)->except(['create']);
    Route::post('/reviews/{review}/complete', [ReviewController::class, 'complete'])->name('reviews.complete');
    Route::post('/reviews/{review}/request-revision', [ReviewController::class, 'requestRevision'])->name('reviews.request-revision');
    Route::post('/reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');

    Route::get('/reports/{review}', [ReviewReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{review}/pdf', [ReviewReportController::class, 'exportPdf'])->name('reports.pdf');

    // AI Summaries
    Route::post('/review-documents/{reviewDocument}/ai-summaries/generate', [AiSummaryController::class, 'generate'])->name('ai-summaries.generate')->middleware('throttle:3,1');
    Route::get('/review-documents/{reviewDocument}/ai-summaries/{aiSummary}/prompt', [AiSummaryController::class, 'checkPrompt'])->name('ai-summaries.check-prompt');
    Route::get('/review-documents/{reviewDocument}/ai-summaries', [AiSummaryController::class, 'index'])->name('ai-summaries.index');
    Route::get('/review-documents/{reviewDocument}/ai-summaries/{aiSummary}', [AiSummaryController::class, 'show'])->name('ai-summaries.show');

    // AI Preview
    Route::get('/review-documents/{reviewDocument}/ai-preview', [AiPreviewController::class, 'show'])->name('ai-preview.show');
    Route::post('/review-documents/{reviewDocument}/ai-preview/generate', [AiPreviewController::class, 'generate'])->name('ai-preview.generate')->middleware('throttle:3,1');
    Route::get('/review-documents/{reviewDocument}/ai-preview/babs-list', [AiPreviewController::class, 'babList'])->name('ai-preview.babs-list');
    Route::post('/review-documents/{reviewDocument}/ai-preview/babs-analyze', [AiPreviewController::class, 'analyzeBabs'])->name('ai-preview.babs-analyze');
    Route::post('/review-documents/{reviewDocument}/ai-preview/bab/{index}', [AiPreviewController::class, 'analyzeSingleBab'])->name('ai-preview.bab');

    // Document Partitions
    Route::get('/review-documents/{reviewDocument}/partitions', [DocumentPartitionController::class, 'index'])->name('partitions.index');
    Route::post('/review-documents/{reviewDocument}/partitions', [DocumentPartitionController::class, 'store'])->name('partitions.store');
    Route::post('/review-documents/{reviewDocument}/partitions/{documentPartition}/extract-toc', [DocumentPartitionController::class, 'extractToc'])->name('partitions.extract-toc');
    Route::get('/review-documents/{reviewDocument}/partitions/{documentPartition}/debug-toc', [DocumentPartitionController::class, 'debugToc'])->name('partitions.debug-toc');
    Route::get('/review-documents/{reviewDocument}/partitions/parsed-text', [DocumentPartitionController::class, 'showParsedText'])->name('partitions.parsed-text');
    Route::get('/review-documents/{reviewDocument}/partitions/regulations', [DocumentPartitionController::class, 'showRegulations'])->name('partitions.regulations');
    Route::post('/review-documents/{reviewDocument}/partitions/analyse', [DocumentPartitionController::class, 'generateAnalysis'])->name('partitions.analyse')->middleware('throttle:3,1');
    Route::post('/review-documents/{reviewDocument}/partitions/extract-regulations', [DocumentPartitionController::class, 'extractRegulations'])->name('partitions.extract-regulations');
    Route::post('/review-documents/{reviewDocument}/partitions/{documentPartition}/analysis', [DocumentPartitionController::class, 'saveAnalysis'])->name('partitions.save-analysis');
    Route::post('/review-documents/{reviewDocument}/partitions/{documentPartition}/detect-structure', [DocumentPartitionController::class, 'detectStructure'])->name('partitions.detect-structure')->middleware('throttle:3,1');
    Route::post('/review-documents/{reviewDocument}/bab-structures/{documentBabStructure}/detect', [DocumentPartitionController::class, 'detectStructure'])->name('bab-structures.detect')->middleware('throttle:3,1');
    Route::post('/review-documents/{reviewDocument}/bab-structures/{documentBabStructure}/detect-ajax', [DocumentPartitionController::class, 'detectStructureAjax'])->name('bab-structures.detect-ajax')->middleware('throttle:3,1');
    Route::post('/review-documents/{reviewDocument}/bab-structures/batch-detect', [DocumentPartitionController::class, 'batchDetectStructure'])->name('bab-structures.batch-detect')->middleware('throttle:3,1');
    Route::post('/review-documents/{reviewDocument}/partitions/parse-pdf', [DocumentPartitionController::class, 'parsePdf'])->name('partitions.parse-pdf')->middleware('throttle:3,1');
    Route::get('/review-documents/{reviewDocument}/partitions/{documentPartition}/content', [DocumentPartitionController::class, 'showPartitionContent'])->name('partitions.content');

    // Regulations browsing (accessible by all authenticated roles)
    Route::get('/regulations/search', [RegulationController::class, 'search'])->name('regulations.search');
    Route::get('/regulations', [RegulationController::class, 'index'])->name('regulations.index');
    Route::get('/regulations/create', [RegulationController::class, 'create'])->name('regulations.create');
    Route::get('/regulations/{regulation}', [RegulationController::class, 'show'])->name('regulations.show');
    Route::post('/regulations/{regulation}/chat', [RegulationChatController::class, 'ask'])->name('regulations.chat.ask')->middleware('throttle:10,1');
    Route::get('/regulations/{regulation}/file', [RegulationController::class, 'viewer'])->name('regulations.file');
    Route::get('/regulations/{regulation}/file/raw', [RegulationController::class, 'viewFile'])->name('regulations.file-raw');
    Route::get('/regulations/documents/{document}/view', [RegulationController::class, 'viewDocument'])->name('regulations.documents.view');

    Route::middleware('role:admin')->group(function () {
        Route::resource('packages', PackageController::class)->except(['show']);
    });

    // Admin management routes (admin & sub_admin only)
    Route::middleware('role:admin,sub_admin')->group(function () {
        Route::get('/legal-necessities', [LegalNecessityController::class, 'index'])->name('legal-necessities.index');

        Route::resource('regulation-categories', RegulationCategoryController::class);
        Route::post('/regulation-categories/{regulationCategory}/upload', [RegulationCategoryController::class, 'uploadFile'])->name('regulation-categories.upload-file');
        Route::delete('/regulation-categories/file/{file}', [RegulationCategoryController::class, 'deleteFile'])->name('regulation-categories.delete-file');
        Route::get('/regulation-categories/file/{file}/view', [RegulationCategoryController::class, 'viewFile'])->name('regulation-categories.view-file');

        Route::post('/regulation-categories/{regulationCategory}/sub-categories', [RegulationCategoryController::class, 'storeSubCategory'])->name('sub-categories.store');
        Route::put('/sub-categories/{subCategory}', [RegulationCategoryController::class, 'updateSubCategory'])->name('sub-categories.update');
        Route::patch('/sub-categories/{subCategory}/toggle', [RegulationCategoryController::class, 'toggleSubCategory'])->name('sub-categories.toggle');
        Route::delete('/sub-categories/{subCategory}', [RegulationCategoryController::class, 'destroySubCategory'])->name('sub-categories.destroy');

        Route::get('/sub-categories', [SubCategoryController::class, 'index'])->name('sub-categories.index');
        Route::post('/sub-categories', [SubCategoryController::class, 'store'])->name('sub-categories.create');
        Route::get('/sub-categories/{subCategory}', [SubCategoryController::class, 'show'])->name('sub-categories.show');

        Route::patch('/regulation-types/{regulationType}/toggle', [RegulationTypeController::class, 'toggle'])->name('regulation-types.toggle');
        Route::resource('regulation-types', RegulationTypeController::class);

        Route::resource('sectors', SectorController::class);

        // Regulations write/parse/analyze (admin & sub_admin)
        Route::resource('regulations', RegulationController::class)->except(['index', 'show', 'create']);
        Route::post('/regulations/{regulation}/parse', [RegulationController::class, 'parseRegulation'])->name('regulations.parse');
        Route::post('/regulations/{regulation}/parse-cancel', [RegulationController::class, 'cancelParse'])->name('regulations.parse-cancel');
        Route::get('/regulations/{regulation}/parse-progress', [RegulationController::class, 'parseProgress'])->name('regulations.parse-progress');
        Route::post('/regulations/{regulation}/extract-references', [RegulationController::class, 'extractReferences'])->name('regulations.extract-references');
        Route::post('/regulations/{regulation}/documents/{document}/parse', [RegulationController::class, 'parseDocument'])->name('regulations.documents.parse');
        Route::post('/regulations/{regulation}/documents/{document}/parse-cancel', [RegulationController::class, 'cancelDocumentParse'])->name('regulations.documents.parse-cancel');
        Route::post('/regulations/{regulation}/parse-documents', [RegulationController::class, 'parseAllDocuments'])->name('regulations.documents.parse-all');
        Route::post('/regulations/{regulation}/documents', [RegulationController::class, 'uploadDocument'])->name('regulations.documents.store');
        Route::put('/regulations/documents/{document}', [RegulationController::class, 'updateDocument'])->name('regulations.documents.update');
        Route::delete('/regulations/documents/{document}', [RegulationController::class, 'deleteDocument'])->name('regulations.documents.destroy');
        Route::get('/regulations/documents/{document}/parsed-text', [RegulationController::class, 'viewDocumentParsedText'])->name('regulations.documents.parsed-text');
        Route::get('/regulations/{regulation}/analyze', [RegulationController::class, 'analyze'])->name('regulations.analyze');
        Route::post('/regulations/{regulation}/analyze/generate', [RegulationController::class, 'generateAnalysis'])->name('regulations.analyze.generate');
        Route::post('/regulations/{regulation}/analyze/save', [RegulationController::class, 'saveAnalysis'])->name('regulations.analyze.save');
        Route::post('/regulations/{regulation}/analyze/connect-references', [RegulationController::class, 'connectReferences'])->name('regulations.analyze.connect-references');
        Route::post('/regulations/{regulation}/reanalyze', [RegulationController::class, 'reanalyze'])->name('regulations.reanalyze');
        Route::post('/regulations/{regulation}/analyze/babs', [RegulationController::class, 'analyzeBabs'])->name('regulations.analyze.babs');
        Route::post('/regulations/{regulation}/analyze/bab/{index}', [RegulationController::class, 'analyzeSingleBab'])->name('regulations.analyze.bab');
        Route::get('/regulations/{regulation}/analyze/babs-list', [RegulationController::class, 'babList'])->name('regulations.analyze.babs-list');
        Route::post('/regulations/{regulation}/generate-ai', [RegulationController::class, 'generateAi'])->name('regulations.generate-ai');

        Route::get('users/register', [UserController::class, 'userFromRegister'])->name('manage.users.from-register');
        Route::resource('users', UserController::class);

        Route::resource('ai-prompts', AiPromptController::class);
        Route::resource('type-prompts', TypePromptController::class);

        Route::resource('legal-cases', LegalCaseController::class);
        Route::post('/legal-cases/{legalCase}/parse', [LegalCaseController::class, 'parse'])->name('legal-cases.parse');
        Route::post('/legal-cases/{legalCase}/generate', [LegalCaseController::class, 'generate'])->name('legal-cases.generate');

        // TEMP DEBUG
        Route::get('/debug-reg-text/{id}', function ($id) {
            $reg = Regulation::find($id);
            if (! $reg || ! $reg->parsed_text) {
                return 'No text';
            }

            return '<pre>'.e(mb_substr($reg->parsed_text, 0, 1000)).'</pre>';
        })->name('debug.reg-text');

        Route::get('/debug-parsed-view', function () {
            try {
                $user = User::first();
                Auth::login($user);
                $rd = ReviewDocument::find(2);

                $debug = [];
                $debug[] = 'User: '.auth()->user()->name;
                $debug[] = "Doc: {$rd->id} - {$rd->title}";
                $debug[] = 'Regs count: '.$rd->regulations()->count();
                $debug[] = 'isParsed: '.($rd->isParsed() ? 'yes' : 'no');

                $reg = $rd->regulations()->first();
                if ($reg) {
                    $debug[] = "Reg {$reg->id}: parsed=".($reg->isParsed() ? 'yes' : 'no').' text_len='.mb_strlen($reg->parsed_text ?? '');
                }

                $result = app(DocumentPartitionController::class)->showParsedText($rd);
                $debug[] = 'Controller returned: '.get_class($result);
                $debug[] = 'View name: '.$result->getName();

                $data = $result->getData();
                $debug[] = 'Regulations in view data: '.count($data['regulations'] ?? []);
                if (! empty($data['regulations'])) {
                    $debug[] = 'First reg has_text: '.($data['regulations'][0]['has_text'] ? 'yes' : 'no');
                    $debug[] = 'First reg main_parsed: '.($data['regulations'][0]['main_parsed'] ? 'yes' : 'no');
                    $debug[] = 'First reg main_chars: '.$data['regulations'][0]['main_chars'];
                }

                $html = $result->render();
                $debug[] = 'HTML length: '.strlen($html);
                $debug[] = 'Has Regulasi Acuan: '.(strpos($html, 'Regulasi Acuan') !== false ? 'yes' : 'no');
                $debug[] = 'Has File Regulasi Utama: '.(strpos($html, 'File Regulasi Utama') !== false ? 'yes' : 'no');
                $debug[] = 'Has OTORITAS: '.(strpos($html, 'OTORITAS') !== false ? 'yes' : 'no');

                return response('<pre>'.implode("\n", $debug).'</pre>');
            } catch (Exception $e) {
                return response('ERROR: '.$e->getMessage()."\nFile: ".$e->getFile().':'.$e->getLine());
            }
        })->name('debug.parsed-view');
    });
});
