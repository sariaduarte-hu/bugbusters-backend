<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $file = $request->file('photo');

        // Unieke bestandsnaam, zodat de browser nooit een oude foto uit de cache toont.
        $path = $file->storeAs(
            'avatars',
            $user->id.'-'.Str::random(12).'.'.$file->extension(),
            'public'
        );

        $oud = $user->profile?->photo;
        $user->profile()->updateOrCreate([], ['photo' => $path]);

        if ($oud) {
            Storage::disk('public')->delete($oud);
        }

        return response()->json($this->format($user->fresh('profile')));
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $oud = $user->profile?->photo;

        if ($oud) {
            Storage::disk('public')->delete($oud);
            $user->profile()->update(['photo' => null]);
        }

        return response()->json($this->format($user->fresh('profile')));
    }

    public function showUser(Request $request, User $user): JsonResponse
    {
        $profile = $user->profile;

        return response()->json([
            'id' => $user->id,
            'is_me' => $request->user()->id === $user->id,
            'name' => $user->name,
            'photo_url' => $user->photoUrl(),
            'age' => $profile?->age,
            'birthday' => $profile?->birthday?->format('Y-m-d'),
            'about' => $profile?->about ?? '',
            'hobbies' => $profile?->hobbies ?? [],
        ]);
    }

    private function format(User $user): array
    {
        $profile = $user->profile;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'photo_url' => $user->photoUrl(),
            'age' => $profile?->age,
            'birthday' => $profile?->birthday?->format('Y-m-d'),
            'about' => $profile?->about ?? '',
            'hobbies' => $profile?->hobbies ?? [],
        ];
    }
}
