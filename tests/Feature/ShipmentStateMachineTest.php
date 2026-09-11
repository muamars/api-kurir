<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentDestination;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShipmentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $driver;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        $adminRole = Role::create(['name' => 'Admin']);
        $driverRole = Role::create(['name' => 'Kurir']);
        $userRole = Role::create(['name' => 'User']);

        Permission::create(['name' => 'approve-shipments']);
        Permission::create(['name' => 'assign-drivers']);
        Permission::create(['name' => 'update-shipments']);

        $adminRole->givePermissionTo(['approve-shipments', 'assign-drivers', 'update-shipments']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole($adminRole);

        $this->driver = User::factory()->create(['is_active' => true]);
        $this->driver->assignRole($driverRole);

        $this->regularUser = User::factory()->create(['is_active' => true]);
        $this->regularUser->assignRole($userRole);
    }

    public function test_update_shipment_cannot_bypass_status()
    {
        $shipment = Shipment::factory()->create([
            'status' => 'pending',
            'created_by' => $this->regularUser->id,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/shipments/{$shipment->shipment_id}", [
            'notes'  => 'Catatan baru',
            'status' => 'completed', // Direct status alteration attempt
        ]);

        $response->assertStatus(200);

        // Status must remain 'pending' despite payload having 'completed'
        $shipment->refresh();
        $this->assertEquals('pending', $shipment->status);
        $this->assertEquals('Catatan baru', $shipment->notes);
    }

    public function test_approve_enforces_active_kurir_role_and_records_audit()
    {
        $shipment = Shipment::factory()->create([
            'status' => 'pending',
            'created_by' => $this->regularUser->id,
        ]);

        // Non-courier assignment should be rejected
        $response = $this->actingAs($this->admin)->postJson("/api/v1/shipments/{$shipment->shipment_id}/approve", [
            'driver_id' => $this->regularUser->id,
        ]);
        $response->assertStatus(422);

        // Inactive courier assignment should be rejected
        $inactiveDriver = User::factory()->create(['is_active' => false]);
        $inactiveDriver->assignRole('Kurir');
        $responseInactive = $this->actingAs($this->admin)->postJson("/api/v1/shipments/{$shipment->shipment_id}/approve", [
            'driver_id' => $inactiveDriver->id,
        ]);
        $responseInactive->assertStatus(422);

        // Active courier assignment should succeed
        $responseSuccess = $this->actingAs($this->admin)->postJson("/api/v1/shipments/{$shipment->shipment_id}/approve", [
            'driver_id' => $this->driver->id,
        ]);
        $responseSuccess->assertStatus(200);

        $shipment->refresh();
        $this->assertEquals('assigned', $shipment->status);
        $this->assertEquals($this->driver->id, $shipment->assigned_driver_id);
        $this->assertEquals($this->admin->id, $shipment->approved_by);

        // Verify audit record exists
        $this->assertDatabaseHas('shipment_status_histories', [
            'shipment_id' => $shipment->id,
            'old_status'  => 'pending',
            'new_status'  => 'assigned',
            'action'      => 'approve',
            'actor_id'    => $this->admin->id,
            'driver_id'   => $this->driver->id,
        ]);
    }

    public function test_takeover_limit_and_supervisor_escalation()
    {
        config(['shipment.max_takeover' => 2]);

        $shipment = Shipment::factory()->create([
            'status'             => 'assigned',
            'assigned_driver_id' => $this->driver->id,
            'takeover_count'     => 1,
            'created_by'         => $this->regularUser->id,
        ]);

        // Takeover 2 (reaches limit 2)
        $res1 = $this->actingAs($this->admin)->postJson("/api/v1/shipments/{$shipment->shipment_id}/takeover");
        $res1->assertStatus(200);
        $shipment->refresh();
        $this->assertEquals('pending', $shipment->status);
        $this->assertEquals(2, $shipment->takeover_count);

        // Reassign back to assigned
        $this->actingAs($this->admin)->postJson("/api/v1/shipments/{$shipment->shipment_id}/approve", [
            'driver_id' => $this->driver->id,
        ]);
        $shipment->refresh();
        $this->assertEquals('assigned', $shipment->status);

        // Takeover 3 (exceeds limit 2) -> must be rejected with 422 and marked needs_review = true
        $res2 = $this->actingAs($this->admin)->postJson("/api/v1/shipments/{$shipment->shipment_id}/takeover");
        $res2->assertStatus(422);

        $shipment->refresh();
        $this->assertTrue($shipment->needs_review);
    }
}
