<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->format($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'about' => ['nullable', 'string', 'max:400'],
            'hobbies' => ['nullable', 'array', 'max:20'],
            'hobbies.*' => ['required', 'string', 'max:30'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated) {
            $user->update(['name' => trim($validated['name'])]);

            $user->profile()->updateOrCreate([], [
                'age' => $validated['age'] ?? null,
                'birthday' => $validated['birthday'] ?? null,
                'about' => isset($validated['about']) ? trim($validated['about']) : null,
                'hobbies' => array_values($validated['hobbies'] ?? []),
            ]);
        });

        return response()->json($this->format($user->fresh('profile')));
    }

    private function format(User $user): array
    {
        $profile = $user->profile;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'age' => $profile?->age,
            'birthday' => $profile?->birthday?->format('Y-m-d'),
            'about' => $profile?->about ?? '',
            'hobbies' => $profile?->hobbies ?? [],
        ];
    }
}
