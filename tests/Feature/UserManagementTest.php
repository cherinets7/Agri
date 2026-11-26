<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHierarchy;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use BuildsHierarchy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHierarchy();
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'አዲስ ባለሙያ', 'email' => 'new@t.test', 'password' => 'secret-pass-1',
            'role' => 'kebele', 'location_id' => $this->kebeleA1->id, 'is_active' => 1,
        ], $o);
    }

    public function test_kebele_users_cannot_manage_users(): void
    {
        $this->actingAs($this->expertA)->get('/users')->assertForbidden();
        $this->actingAs($this->expertA)->post('/users', $this->payload())->assertForbidden();
    }

    public function test_zone_creates_users_with_matching_location_level(): void
    {
        $this->actingAs($this->zoneUser)->post('/users', $this->payload())->assertRedirect('/users');
        $this->assertSame(Role::Kebele, User::where('email', 'new@t.test')->firstOrFail()->role);

        $this->actingAs($this->zoneUser)->post('/users', $this->payload(['email' => 'x@t.test', 'role' => 'woreda']))->assertStatus(422);
    }

    public function test_woreda_manages_only_own_kebele_experts(): void
    {
        $this->actingAs($this->woredaUserA)->post('/users', $this->payload())->assertRedirect('/users');
        $this->actingAs($this->woredaUserA)->post('/users', $this->payload(['email' => 'b@t.test', 'location_id' => $this->kebeleB1->id]))->assertStatus(422);
        $this->actingAs($this->woredaUserA)->post('/users', $this->payload(['email' => 'c@t.test', 'role' => 'zone', 'location_id' => $this->zone->id]))->assertSessionHasErrors('role');
        $this->actingAs($this->woredaUserA)->get(route('users.edit', $this->expertB))->assertForbidden();
        $this->actingAs($this->woredaUserA)->get(route('users.edit', $this->expertA))->assertOk();
    }

    public function test_locations_zone_adds_woreda_and_woreda_adds_kebele_in_own_woreda_only(): void
    {
        $this->actingAs($this->zoneUser)->post('/locations', ['parent_id' => $this->zone->id, 'name_am' => 'አዲስ ወረዳ'])->assertRedirect('/locations');
        $this->assertDatabaseHas('locations', ['name_am' => 'አዲስ ወረዳ', 'level' => 'woreda']);

        $this->actingAs($this->woredaUserA)->post('/locations', ['parent_id' => $this->woredaA->id, 'name_am' => 'አዲስ ቀበሌ'])->assertRedirect('/locations');
        $this->assertDatabaseHas('locations', ['name_am' => 'አዲስ ቀበሌ', 'level' => 'kebele']);
        $this->actingAs($this->woredaUserA)->post('/locations', ['parent_id' => $this->woredaB->id, 'name_am' => 'ሌላ'])->assertForbidden();
        $this->actingAs($this->expertA)->get('/locations')->assertForbidden();
    }
}
