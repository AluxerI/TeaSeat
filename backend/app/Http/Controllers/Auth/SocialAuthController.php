<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Resources\UserResource;

class SocialAuthController extends Controller
{
        public function logout(Request $request)
    {
        try {
            // Для аутентификации по API: удаляем все токены пользователя
            $request->user()->tokens()->delete();

            // Если используется веб-аутентификация (сессии), добавьте:
            // Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Успешный выход из системы.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Logout failed: ' . $e->getMessage()
            ], 401);
        }
    }
    public function handleProviderCallback(Request $request, $provider)
    {
        try {
            // Верифицируем данные через API провайдера
            $userData = $this->verifyProviderData($provider, $request->all());

            // Ищем или создаем пользователя
            $user = User::firstOrCreate(
            ['provider' => $provider, 'provider_id' => $userData['id']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'] ?? null,
                    'email_verified_at' => $provider === 'google' ? now() : null, // Автоверификация для Google
                    'password' => Hash::make(Str::random(24)),
                    'is_active' => true,
                ]
            );

            if (!$user->is_active) {
                throw new \RuntimeException('Аккаунт заблокирован.');
            }

            if (!$user->roles()->exists()) {
                $user->assignRole(User::ROLE_USER);
            }

            // Создаем Sanctum токен
            $token = $user->createToken('social-auth-' . $provider)->plainTextToken;

            return response()->json([
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user->load(['roles', 'activeWarehouses'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Invalid authentication data: ' . $e->getMessage()
            ], 401);
        }
    }

    protected function verifyProviderData($provider, $requestData)
    {
        switch ($provider) {
            case 'google':
                return $this->verifyGoogleToken($requestData['token']);
            case 'vkontakte':
                return $this->verifyVkToken($requestData['token']);
            case 'telegram':
                return $this->verifyTelegramData($requestData['data']);
            default:
                throw new \Exception('Unsupported provider');
        }
    }

    protected function verifyGoogleToken($token)
    {
        // Упрощенная проверка - в реальности используйте Google API Client
        $clientId = config('services.google.client_id');
        
        // Здесь должна быть реальная проверка через Google API
        // Пока просто декодируем JWT для демонстрации
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid Google token format');
        }
        
        $payload = json_decode(base64_decode($parts[1]), true);
        
        return [
            'id' => $payload['sub'],
            'name' => $payload['name'],
            'email' => $payload['email']
        ];
    }

    protected function verifyVkToken($token)
    {
        // Запрос к VK API для проверки токена
        $response = file_get_contents(
            "https://api.vk.com/method/users.get?access_token={$token}&v=5.131"
        );
        
        $data = json_decode($response, true);
        
        if (!isset($data['response'][0]['id'])) {
            throw new \Exception('Invalid VK token');
        }
        
        $userInfo = $data['response'][0];
        
        return [
            'id' => $userInfo['id'],
            'name' => $userInfo['first_name'] . ' ' . $userInfo['last_name'],
            'email' => null // VK не возвращает email через этот метод
        ];
    }

    protected function verifyTelegramData($data)
    {
        // Проверка данных Telegram Web App
        if (!isset($data['id'])) {
            throw new \Exception('Invalid Telegram data');
        }
        
        return [
            'id' => $data['id'],
            'name' => $data['first_name'] . ' ' . ($data['last_name'] ?? ''),
            'email' => null // Telegram не предоставляет email
        ];
    }


}
