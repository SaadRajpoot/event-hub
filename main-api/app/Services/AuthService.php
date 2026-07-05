<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function registerAttendee(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'role'     => 'attendee',
            ]);

            Attendee::create([
                'user_id' => $user->id,
                'phone'   => $data['phone'] ?? null,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return ['user' => $user->load('attendee'), 'token' => $token];
        });
    }

    public function registerVendor(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'role'     => 'vendor',
            ]);

            $slug = \Illuminate\Support\Str::slug($data['business_name']);
            $originalSlug = $slug;
            $count = 1;
            while (Vendor::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }

            Vendor::create([
                'user_id'       => $user->id,
                'business_name' => $data['business_name'],
                'slug'          => $slug,
                'contact_email' => $data['contact_email'] ?? $data['email'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'status'        => 'pending',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return ['user' => $user->load('vendor'), 'token' => $token];
        });
    }

    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['vendor', 'attendee']);

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
