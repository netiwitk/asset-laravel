<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * @return array{token: string, name: string, role: string}
     */
    public function login(Request $request): array
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user?->is_active || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']);
        }

        return $this->issue($user);
    }

    /**
     * Demo mode only, like the web's one-click logins: a portfolio visitor tries a role without a password.
     *
     * @return array{token: string, name: string, role: string}
     */
    public function demo(string $account): array
    {
        abort_unless(config('app.demo'), 404);

        return $this->issue(User::query()->where('email', "{$account}@demo.test")->firstOrFail());
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * @return array{token: string, name: string, role: string}
     */
    private function issue(User $user): array
    {
        return [
            'token' => $user->createToken('scanner')->plainTextToken,
            'name' => $user->name,
            'role' => $user->role->getLabel(),
        ];
    }
}
