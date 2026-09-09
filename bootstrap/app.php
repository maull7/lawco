<?php

use App\Http\Middleware\ApplySectorVisibility;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ApplySectorVisibility::class);
        $middleware->alias([
            'role' => EnsureRole::class,
            'profile.complete' => EnsureProfileComplete::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = 'Ukuran file yang diunggah melebihi batas maksimal yang diizinkan server. Kompres file atau unggah file di bawah 20 MB.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['file' => [$message]]], 413);
            }

            // Session may not be started this early in the pipeline; fall back to a plain readable page.
            if ($request->hasSession() && $request->headers->has('referer')) {
                return back()->withInput()->withErrors(['file' => $message]);
            }

            return response($message, 413);
        });
    })->create();
