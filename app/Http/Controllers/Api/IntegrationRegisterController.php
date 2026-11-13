<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Registrasi akun server-to-server dari project utama (mis. pembelian paket gratis).
 * Akun yang sudah ada tidak pernah diubah dan password-nya tidak ditimpa.
 */
class IntegrationRegisterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            // Hash bcrypt dari project utama, disimpan apa adanya (tidak di-hash ulang).
            // Cost tidak boleh melebihi BCRYPT_ROUNDS lokal, jika lebih cast `hashed` akan menolak.
            'password_hash' => ['required', 'string', 'regex:/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/',
                function (string $attribute, string $value, \Closure $fail) {
                    if ((int) substr($value, 4, 2) > (int) config('hashing.bcrypt.rounds', 12)) {
                        $fail('Cost bcrypt password_hash melebihi konfigurasi aplikasi tujuan.');
                    }
                }],
        ]);
        $email = strtolower($data['email']);

        // createOrFirst bergantung pada unique index users.email, aman dari request paralel.
        $user = User::createOrFirst(['email' => $email], [
            'name' => $data['name'],
            'password' => $data['password_hash'],
            'role' => 'user',
        ]);
        $isCreated = $user->wasRecentlyCreated;
        $status = $isCreated ? 'registered' : 'already_registered';

        // Email sudah diverifikasi project utama, jadi akun integrasi langsung aktif tanpa email verifikasi.
        if ($isCreated) {
            $user->markEmailAsVerified();
        }

        Log::info('integration.register', [
            'client' => $request->attributes->get('integrationClient'),
            'email' => $email,
            'status' => $status,
        ]);

        return response()->json([
            'success' => true,
            'message' => $isCreated ? 'Akun berhasil didaftarkan' : 'Akun sudah terdaftar',
            'data' => ['name' => $user->name, 'email' => $user->email, 'status' => $status],
        ], $isCreated ? 201 : 200);
    }
}
