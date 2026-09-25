<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\TenantContextMissingException;
use App\Facades\Tenancy;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Fixtures\TenantFixture;
use Tests\TestCase;

class AccountScopeTest extends TestCase
{
    use RefreshDatabase;

    private Account $acme;

    private Account $globex;

    protected function setUp(): void
    {
        parent::setUp();

        TenantFixture::migrate();

        $this->acme = Account::factory()->create(['name' => 'Acme']);
        $this->globex = Account::factory()->create(['name' => 'Globex']);
    }

    public function test_queries_only_return_records_from_the_current_account(): void
    {
        Tenancy::for($this->acme, fn () => TenantFixture::create(['name' => 'Acme note']));
        Tenancy::for($this->globex, fn () => TenantFixture::create(['name' => 'Globex note']));

        Tenancy::set($this->acme);

        $this->assertSame(['Acme note'], TenantFixture::query()->pluck('name')->all());
    }

    public function test_creating_a_record_fills_the_current_account_id(): void
    {
        Tenancy::set($this->acme);

        $fixture = TenantFixture::create(['name' => 'Filled']);

        $this->assertSame($this->acme->id, $fixture->account_id);
    }

    public function test_creating_a_record_for_another_account_throws(): void
    {
        Tenancy::set($this->acme);

        $this->expectException(CrossTenantWriteException::class);

        $fixture = new TenantFixture(['name' => 'Smuggled']);
        $fixture->account_id = $this->globex->id;
        $fixture->save();
    }

    public function test_moving_a_record_to_another_account_throws(): void
    {
        Tenancy::set($this->acme);

        $fixture = TenantFixture::create(['name' => 'Settled']);

        $this->expectException(CrossTenantWriteException::class);

        $fixture->account_id = $this->globex->id;
        $fixture->save();
    }

    public function test_querying_without_a_tenant_context_throws(): void
    {
        $this->expectException(TenantContextMissingException::class);

        TenantFixture::query()->get();
    }

    public function test_creating_without_a_tenant_context_throws(): void
    {
        $this->expectException(TenantContextMissingException::class);

        TenantFixture::create(['name' => 'Orphan']);
    }

    public function test_without_tenancy_reads_across_every_account(): void
    {
        Tenancy::for($this->acme, fn () => TenantFixture::create(['name' => 'Acme note']));
        Tenancy::for($this->globex, fn () => TenantFixture::create(['name' => 'Globex note']));

        $names = Tenancy::withoutTenancy(fn () => TenantFixture::query()->pluck('name')->all());

        $this->assertEqualsCanonicalizing(['Acme note', 'Globex note'], $names);
    }

    public function test_without_tenancy_still_requires_an_explicit_account_id_on_create(): void
    {
        $this->expectException(TenantContextMissingException::class);

        Tenancy::withoutTenancy(fn () => TenantFixture::create(['name' => 'Ambiguous']));
    }

    public function test_tenancy_for_restores_the_previous_context(): void
    {
        Tenancy::set($this->acme);

        Tenancy::for($this->globex, function (): void {
            $this->assertSame($this->globex->id, Tenancy::id());
        });

        $this->assertSame($this->acme->id, Tenancy::id());
    }

    public function test_tenancy_for_restores_the_previous_context_when_the_callback_throws(): void
    {
        Tenancy::set($this->acme);

        try {
            Tenancy::for($this->globex, fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
            // Expected — the point is what the context looks like afterwards.
        }

        $this->assertSame($this->acme->id, Tenancy::id());
    }

    public function test_find_does_not_return_a_record_from_another_account(): void
    {
        $theirs = Tenancy::for($this->globex, fn () => TenantFixture::create(['name' => 'Theirs']));

        Tenancy::set($this->acme);

        $this->assertNull(TenantFixture::find($theirs->id));
    }
}
