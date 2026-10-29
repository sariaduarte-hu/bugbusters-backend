<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Het profiel van de ingelogde gebruiker ophalen.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $profile = $user->profile()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'profile' => $profile,
        ]);
    }

    /**
     * Het profiel van de ingelogde gebruiker bijwerken.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'birthday' => ['sometimes', 'nullable', 'date', 'before:today'],
            'about' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'hobbies' => ['sometimes', 'array', 'max:30'],
            'hobbies.*' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
            $user->save();
        }

        $profileData = collect($validated)->only([
            'birthday',
            'about',
            'hobbies',
        ])->all();

        $profile = $user->profile()->firstOrCreate(
            ['user_id' => $user->id]
        );

        if (!empty($profileData)) {
            $profile->fill($profileData);
            $profile->save();
        }

        return response()->json([
            'message' => 'Profiel bijgewerkt.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'profile' => $profile->fresh(),
        ]);
    }
}