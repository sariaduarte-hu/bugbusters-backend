<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $allowedDomains = config('registration.allowed_email_domains');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => [
                'required',
                'email',
                'unique:users,email',
                function ($attribute, $value, $fail) use ($allowedDomains) {
                    $domain = strtolower(substr(strrchr($value, '@') ?: '', 1));

                    if (!in_array($domain, $allowedDomains, true)) {
                        $fail('Gebruik een goedgekeurd HU-e-mailadres.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validated);

        return response()->json([
            'message' => 'Registratie geslaagd.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Het e-mailadres of wachtwoord is onjuist.',
            ], 401);
        }

        $token = $user->createToken('campusconnect-web')->plainTextToken;

        return response()->json([
            'message' => 'Inloggen geslaagd.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Uitloggen geslaagd.',
        ]);
    }
}