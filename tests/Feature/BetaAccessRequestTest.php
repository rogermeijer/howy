<?php

namespace Tests\Feature;

use App\Models\BetaAccessRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BetaAccessRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_request_access(): void
    {
        $this->from(route('home'))
            ->post(route('beta-access.store'), ['email' => 'Someone@Example.com'])
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        $request = BetaAccessRequest::query()->sole();

        $this->assertSame('someone@example.com', $request->email);
        $this->assertNotNull($request->locale);
    }

    public function test_asking_twice_keeps_one_request(): void
    {
        $this->post(route('beta-access.store'), ['email' => 'someone@example.com']);
        $this->post(route('beta-access.store'), ['email' => 'SOMEONE@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, BetaAccessRequest::query()->count());
    }

    public function test_the_email_must_be_valid(): void
    {
        $this->post(route('beta-access.store'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, BetaAccessRequest::query()->count());
    }
}
