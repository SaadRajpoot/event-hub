<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@eventhub.local'],
            [
                'name'     => 'Platform Admin',
                'password' => Hash::make('Admin@123456'),
                'role'     => 'admin',
            ]
        );

        // Seed default platform settings
        $settings = [
            ['key' => 'platform_commission_rate', 'value' => '0.10', 'type' => 'decimal', 'description' => 'Default platform commission rate (10%)'],
            ['key' => 'order_expiry_minutes',     'value' => '15',   'type' => 'integer', 'description' => 'Minutes before unpaid order expires'],
            ['key' => 'max_tickets_per_order',    'value' => '10',   'type' => 'integer', 'description' => 'Maximum tickets per order'],
            ['key' => 'platform_name',            'value' => 'EventHub', 'type' => 'string', 'description' => 'Platform display name'],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
