<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $allowedDomains = config('registration.allowed_email_domains');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
                function ($attribute, $value, $fail) use ($allowedDomains) {
                    $domain = strtolower(substr(strrchr($value, '@') ?: '', 1));

                    if (! in_array($domain, $allowedDomains, true)) {
                        $fail('Gebruik een goedgekeurd HU-emailadres.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validated);
        $user->profile()->create();

        return response()->json([
            'message' => 'Registration successful.',
            'token' => $user->createToken('web')->plainTextToken,
            'user' => $this->userData($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower(trim($validated['email'])))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'E-mailadres of wachtwoord klopt niet.',
            ], 422);
        }

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => $this->userData($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Uitgelogd.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userData($request->user()));
    }

        public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Wachtwoord klopt niet.'], 422);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Account verwijderd.']);
    }

        public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Vul je huidige wachtwoord in.',
            'password.required' => 'Vul een nieuw wachtwoord in.',
            'password.min' => 'Je nieuwe wachtwoord moet minstens 8 tekens zijn.',
            'password.confirmed' => 'De twee nieuwe wachtwoorden komen niet overeen.',
            'password.different' => 'Je nieuwe wachtwoord moet anders zijn dan je huidige.',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Je huidige wachtwoord klopt niet.'], 422);
        }

        $user->update(['password' => $validated['password']]);

        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return response()->json(['message' => 'Wachtwoord gewijzigd.']);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
