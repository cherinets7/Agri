<?php

namespace Tests\Concerns;

use App\Enums\Level;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;

trait BuildsHierarchy
{
    protected Location $zone;

    protected Location $woredaA;

    protected Location $woredaB;

    protected Location $kebeleA1;

    protected Location $kebeleB1;

    protected User $zoneUser;

    protected User $woredaUserA;

    protected User $woredaUserB;

    protected User $expertA;

    protected User $expertB;

    protected function buildHierarchy(): void
    {
        $this->zone = Location::create(['level' => Level::Zone, 'name_am' => 'የየም ዞን']);
        $this->woredaA = Location::create(['level' => Level::Woreda, 'name_am' => 'ፎፋ', 'parent_id' => $this->zone->id]);
        $this->woredaB = Location::create(['level' => Level::Woreda, 'name_am' => 'ቶባ', 'parent_id' => $this->zone->id]);
        $this->kebeleA1 = Location::create(['level' => Level::Kebele, 'name_am' => 'ቀበሌ ሀ', 'parent_id' => $this->woredaA->id]);
        $this->kebeleB1 = Location::create(['level' => Level::Kebele, 'name_am' => 'ቀበሌ ለ', 'parent_id' => $this->woredaB->id]);

        $this->zoneUser = $this->makeUser(Role::Zone, $this->zone, 'zone@t.test');
        $this->woredaUserA = $this->makeUser(Role::Woreda, $this->woredaA, 'wa@t.test');
        $this->woredaUserB = $this->makeUser(Role::Woreda, $this->woredaB, 'wb@t.test');
        $this->expertA = $this->makeUser(Role::Kebele, $this->kebeleA1, 'ka@t.test');
        $this->expertB = $this->makeUser(Role::Kebele, $this->kebeleB1, 'kb@t.test');
    }

    protected function makeUser(Role $role, Location $location, string $email): User
    {
        return User::factory()->create(['role' => $role, 'location_id' => $location->id, 'email' => $email]);
    }

    protected function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'report_date' => today()->toDateString(),
            'farmers_total' => 8,
            'new_farmers' => 3,
            'repeat_farmers' => 5,
            'female_farmers' => 4,
            'youth_farmers' => 2,
            'service_types' => ['crop'],
            'techniques' => ['household_visit'],
            'duration_minutes' => 240,
            'notes' => 'ሙከራ',
        ], $overrides);
    }
}
