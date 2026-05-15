<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetCabangDatabase
{
     public function handle(Request $request, Closure $next)
    {
        // 🔥 AMBIL DARI SESSION (bukan header)
        $cabangDatabase = session('cabang_database');
        $cabangKode = session('user.cabang_kode');
        
        Log::info('SetCabangDatabase', [
            'cabang_database' => $cabangDatabase,
            'cabang_kode' => $cabangKode
        ]);
        
        if (!$cabangDatabase) {
            return response()->json([
                'success' => false,
                'message' => 'Cabang tidak dipilih. Silakan login ulang.'
            ], 401);
        }
        
        // Switch ke database cabang
        DatabaseManager::switchToCabang($cabangDatabase);
        
        // 🔥 SIMPAN KODE CABANG KE REQUEST UNTUK DIGUNAKAN CONTROLLER
        $request->merge(['cabang_kode' => $cabangKode]);
        
        return $next($request);
    }
}