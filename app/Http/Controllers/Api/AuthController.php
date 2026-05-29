<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pusat\Cabang;
use App\Services\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);
            
            // Cek user di database (tanpa cabang)
            $user = DB::connection('mysql')
                ->table('tuser')
                ->where('USER_KODE', $request->username)
                ->where('USER_PASSWORD', $request->password)
                ->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau password salah'
                ], 401);
            }
            
            // Simpan session (tanpa data cabang)
            session()->regenerate();
            session([
        'user' => [
            'id' => $user->user_id,           
            'kode' => $user->USER_KODE,
            'nama' => $user->USER_NAMA ?? $user->USER_KODE,
            'user_id' => $user->user_id,   
        ]
    ]);
            session()->save();
            
            Log::info('Login berhasil', ['user' => $user->USER_KODE]);
            
            return response()->json([
        'success' => true,
        'message' => 'Login berhasil',
        'data' => [
            'user' => session('user')
        ]
    ]);
            
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Login gagal: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getUser(Request $request)
    {
        if (!session('user')) {
            return response()->json([
                'success' => false,
                'message' => 'Belum login'
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'data' => session('user')
        ]);
    }
    
    public function logout(Request $request)
    {
        session()->flush();
        
        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}
