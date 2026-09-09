@extends('layouts.app')

@section('title', 'Upload Document')
@section('header', 'Upload Document')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                <x-slot name="header">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">New Submission</p>
                        <h3 class="mt-1 text-xl font-bold text-[#071833]">Upload Document for Review</h3>
                        <p class="text-sm text-[#667085] mt-1">Provide details and attach the document to be assessed by compliance reviewers.</p>
                    </div>
                </x-slot>

                <form method="POST" action="{{ route('review-documents.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <label for="title" class="block text-sm font-semibold text-[#071833] mb-2">Document Title <span class="text-[#c99a3e]">*</span></label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" required class="input-premium" placeholder="e.g. Quarterly Compliance Disclosure">
                        @error('title')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-[#071833] mb-2">Description</label>
                        <textarea name="description" id="description" rows="4" class="input-premium input-textarea" placeholder="Brief context, scope, or summary of the document…">{{ old('description') }}</textarea>
                        @error('description')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="file" class="block text-sm font-semibold text-[#071833] mb-2">Document File <span class="text-[#c99a3e]">*</span></label>
                        <input type="file" name="file" id="file" accept=".pdf,.doc,.docx" required class="file-premium">
                        <p class="mt-1.5 text-xs text-[#667085]">Accepted formats: PDF, DOC, DOCX — maximum 20 MB.</p>
                        @error('file')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        @include('review-documents._regulation-picker', ['selectedIds' => old('regulation_ids', []), 'showFilters' => true])
                        @error('regulation_ids')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-[#e7eaf0]">
                        <x-button type="submit" variant="primary" size="lg">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Upload Document
                        </x-button>
                        <x-button href="{{ route('review-documents.index') }}" variant="outline" size="lg">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <aside>
            <x-card>
                <x-slot name="header">
                    <h3 class="text-base font-bold text-[#071833]">Submission Guidelines</h3>
                </x-slot>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <span class="shrink-0 w-7 h-7 rounded-lg bg-[#c99a3e]/15 text-[#c99a3e] flex items-center justify-center text-[11px] font-bold">1</span>
                        <div>
                            <p class="text-sm font-semibold text-[#071833]">Clear & descriptive title</p>
                            <p class="text-xs text-[#667085] mt-0.5">Helps reviewers quickly identify your submission.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="shrink-0 w-7 h-7 rounded-lg bg-[#c99a3e]/15 text-[#c99a3e] flex items-center justify-center text-[11px] font-bold">2</span>
                        <div>
                            <p class="text-sm font-semibold text-[#071833]">Relevant categories</p>
                            <p class="text-xs text-[#667085] mt-0.5">Select all regulations applicable to your document.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="shrink-0 w-7 h-7 rounded-lg bg-[#c99a3e]/15 text-[#c99a3e] flex items-center justify-center text-[11px] font-bold">3</span>
                        <div>
                            <p class="text-sm font-semibold text-[#071833]">High-quality file</p>
                            <p class="text-xs text-[#667085] mt-0.5">Upload the final version — searchable PDF preferred.</p>
                        </div>
                    </li>
                </ul>

                <div class="mt-6 p-4 rounded-2xl bg-gradient-to-br from-[#071b3a] to-[#0b2a55] text-white">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#e6c06a]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <p class="text-xs font-bold uppercase tracking-wider text-[#e6c06a]">Confidential</p>
                    </div>
                    <p class="mt-2 text-xs text-white/75 leading-relaxed">Your documents are encrypted in transit and at rest. Only authorized reviewers can access them.</p>
                </div>
            </x-card>
        </aside>
    </div>
@endsection
