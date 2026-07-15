<?php

namespace Tests\Feature\Staff;

use App\Filament\Resources\UserResource as FilamentUserResource;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StaffAccessService;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_roles_are_idempotent_and_one_account_can_combine_staff_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles([
            User::ROLE_SELLER,
            User::ROLE_COURIER,
            User::ROLE_PICKER,
            User::ROLE_MANAGER,
        ]);

        $this->assertSame(6, Role::count());
        $this->assertTrue($user->hasAllRoles([
            User::ROLE_SELLER,
            User::ROLE_COURIER,
            User::ROLE_PICKER,
            User::ROLE_MANAGER,
        ]));
        $this->assertTrue($user->can('create seller orders'));
        $this->assertTrue($user->can('view assigned deliveries'));
        $this->assertTrue($user->can('manage own picking orders'));
        $this->assertTrue($user->can('manage orders'));
    }

    public function test_active_location_links_can_be_switched_without_losing_history(): void
    {
        $seller = User::factory()->create();
        $seller->syncRoles([User::ROLE_SELLER, User::ROLE_COURIER]);
        $firstLocation = Warehouse::factory()->physicalStore()->create();
        $secondLocation = Warehouse::factory()->create();
        $service = app(StaffAccessService::class);

        $service->syncActiveLocations($seller, [
            $firstLocation->id,
            $secondLocation->id,
        ]);
        $service->syncActiveLocations($seller, [$secondLocation->id]);

        $this->assertDatabaseHas('user_warehouse', [
            'user_id' => $seller->id,
            'warehouse_id' => $firstLocation->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('user_warehouse', [
            'user_id' => $seller->id,
            'warehouse_id' => $secondLocation->id,
            'is_active' => true,
        ]);
        $this->assertFalse($seller->fresh()->hasWarehouseAccess($firstLocation->id));
        $this->assertTrue($seller->fresh()->hasWarehouseAccess($secondLocation->id));

        $seller->assignRole(User::ROLE_MANAGER);

        $this->assertTrue($seller->fresh()->hasWarehouseAccess($firstLocation->id));
        $this->assertSame(2, DB::table('user_warehouse')->where('user_id', $seller->id)->count());
    }

    public function test_customer_cannot_be_assigned_to_a_staff_location(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(User::ROLE_USER);
        $store = Warehouse::factory()->physicalStore()->create();

        $this->expectException(DomainException::class);

        app(StaffAccessService::class)->syncActiveLocations($customer, [$store->id]);
    }

    public function test_only_active_admins_and_managers_can_open_filament(): void
    {
        $panel = Panel::make()->id('admin');
        $admin = User::factory()->create();
        $manager = User::factory()->create();
        $seller = User::factory()->create();
        $courier = User::factory()->create();
        $picker = User::factory()->create();
        $customer = User::factory()->create();

        $admin->assignRole(User::ROLE_ADMIN);
        $manager->assignRole(User::ROLE_MANAGER);
        $seller->assignRole(User::ROLE_SELLER);
        $courier->assignRole(User::ROLE_COURIER);
        $picker->assignRole(User::ROLE_PICKER);
        $customer->assignRole(User::ROLE_USER);

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($manager->canAccessPanel($panel));
        $this->assertFalse($seller->canAccessPanel($panel));
        $this->assertFalse($courier->canAccessPanel($panel));
        $this->assertFalse($picker->canAccessPanel($panel));
        $this->assertFalse($customer->canAccessPanel($panel));

        $manager->update(['is_active' => false]);

        $this->assertFalse($manager->fresh()->canAccessPanel($panel));
    }

    public function test_manager_cannot_manage_user_roles_in_filament(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->create();
        $manager->assignRole(User::ROLE_MANAGER);
        $admin->assignRole(User::ROLE_ADMIN);

        $this->actingAs($manager);
        $this->assertFalse(FilamentUserResource::canViewAny());
        $this->assertFalse(FilamentUserResource::canEdit($admin));

        $this->actingAs($admin);
        $this->assertTrue(FilamentUserResource::canViewAny());
        $this->assertTrue(FilamentUserResource::canEdit($manager));
    }

    public function test_login_returns_all_roles_capabilities_and_active_locations(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@teaseat.test',
            'password' => 'password',
        ]);
        $user->syncRoles([
            User::ROLE_SELLER,
            User::ROLE_COURIER,
            User::ROLE_PICKER,
            User::ROLE_MANAGER,
        ]);
        $store = Warehouse::factory()->physicalStore()->create([
            'name' => 'Магазин для PWA',
        ]);
        app(StaffAccessService::class)->syncActiveLocations($user, [$store->id]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'staff@teaseat.test',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.capabilities.seller', true)
            ->assertJsonPath('user.capabilities.courier', true)
            ->assertJsonPath('user.capabilities.picker', true)
            ->assertJsonPath('user.capabilities.manager', true)
            ->assertJsonPath('user.capabilities.can_access_filament', true)
            ->assertJsonPath('user.work_locations.0.id', $store->id)
            ->assertJsonPath('user.work_locations.0.type', Warehouse::TYPE_STORE);

        $this->assertIsString($response->json('token'));
        $this->assertNotSame('', $response->json('token'));
        $this->assertEqualsCanonicalizing([
            User::ROLE_SELLER,
            User::ROLE_COURIER,
            User::ROLE_PICKER,
            User::ROLE_MANAGER,
        ], $response->json('user.roles'));
    }

    public function test_customer_profile_has_no_staff_capabilities_or_locations(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(User::ROLE_USER);
        Sanctum::actingAs($customer);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.roles.0', User::ROLE_USER)
            ->assertJsonPath('data.work_locations', [])
            ->assertJsonPath('data.capabilities.admin', false)
            ->assertJsonPath('data.capabilities.manager', false)
            ->assertJsonPath('data.capabilities.seller', false)
            ->assertJsonPath('data.capabilities.picker', false)
            ->assertJsonPath('data.capabilities.courier', false)
            ->assertJsonPath('data.capabilities.can_access_filament', false);
    }

    public function test_deactivated_user_cannot_login_and_existing_tokens_are_revoked(): void
    {
        $user = User::factory()->create([
            'email' => 'blocked@teaseat.test',
            'password' => 'password',
        ]);
        $user->assignRole(User::ROLE_SELLER);
        $user->createToken('existing-token');

        $user->update(['is_active' => false]);

        $this->assertSame(0, $user->tokens()->count());

        $this->postJson('/api/auth/login', [
            'email' => 'blocked@teaseat.test',
            'password' => 'password',
        ])->assertUnauthorized();
    }
}
