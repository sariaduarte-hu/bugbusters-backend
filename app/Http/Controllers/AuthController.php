<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
    {
    public function register(Request $request)
    {
    
        $allowedDomains = config('registration.allowed_email_domains');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 
                'email', 
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
        return response()->json([
            'message' => 'Registration successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }
}
