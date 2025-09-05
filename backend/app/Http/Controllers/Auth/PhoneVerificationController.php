<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Twilio\Rest\Client;

class PhoneVerificationController extends Controller
{
        public function sendVerificationCode(Request $request)
    {
        try {
            $request->validate(['phone' => 'required|phone:RU']);
            
            $code = rand(1000, 9999);
            $expiresAt = now()->addMinutes(10);
            
            PhoneVerificationCode::updateOrCreate(
                ['phone' => $request->phone],
                ['code' => $code, 'expires_at' => $expiresAt]
            );
        
            $twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
            
            $twilio->messages->create(
                $request->phone,
                [
                    'from' => env('TWILIO_PHONE_NUMBER'),
                    'body' => "Ваш код подтверждения: $code"
                ]
            );
        
            return response()->json(['message' => 'Код отправлен']);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ошибка отправки SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    // 2. Проверка кода
    public function verifyPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|phone:RU',
            'code' => 'required|numeric'
        ]);

        $verificationCode = PhoneVerificationCode::where('phone', $request->phone)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verificationCode) {
            return response()->json(['error' => 'Неверный код'], 422);
        }

        // Помечаем телефон как подтвержденный
        $user = User::where('phone', $request->phone)->first();
        $user->update(['phone_verified_at' => now()]);

        $verificationCode->delete();

        return response()->json(['message' => 'Телефон подтвержден']);
    }
}
