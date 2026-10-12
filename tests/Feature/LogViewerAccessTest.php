<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('log-viewer.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_super_admin_users_cannot_open_log_viewer(): void
    {
        $user = User::factory()->withAccount()->create();
        $this->actingAs($user);

        $this->get(route('log-viewer.index'))
            ->assertForbidden();
    }

    public function test_super_admins_can_open_log_viewer(): void
    {
        $user = User::factory()->superAdmin()->withAccount()->create();
        $this->actingAs($user);

        $this->get(route('log-viewer.index'))
            ->assertOk();
    }
}
