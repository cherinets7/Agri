<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LocationSeeder::class);
        $this->call(PlanMatrixSeeder::class);

        if (env('ADMIN_EMAIL') && env('ADMIN_PASSWORD')) {
            User::updateOrCreate(['email' => env('ADMIN_EMAIL')], [
                'name' => env('ADMIN_NAME', 'የዞን አስተዳዳሪ'),
                'password' => env('ADMIN_PASSWORD'),
                'role' => Role::Zone,
                'location_id' => Location::ofLevel(Level::Zone)->value('id'),
                'is_active' => true,
            ]);
        }

        if (app()->environment('local', 'testing') || filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
