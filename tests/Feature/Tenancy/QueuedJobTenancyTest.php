<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\TenantContextMissingException;
use App\Facades\Tenancy;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\RecordCurrentAccountJob;
use Tests\TestCase;

class QueuedJobTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RecordCurrentAccountJob::$seenAccountId = null;
    }

    public function test_a_job_runs_in_the_account_that_dispatched_it(): void
    {
        $account = Account::factory()->create();

        $job = Tenancy::for($account, fn () => new RecordCurrentAccountJob);

        // The worker has no context of its own, so this is the real test: without
        // the trait's middleware the job would throw rather than see an account.
        $this->assertFalse(Tenancy::check());

        $this->dispatchThroughMiddleware($job);

        $this->assertSame($account->id, RecordCurrentAccountJob::$seenAccountId);
    }

    public function test_the_context_does_not_leak_after_the_job_finishes(): void
    {
        $account = Account::factory()->create();

        $job = Tenancy::for($account, fn () => new RecordCurrentAccountJob);

        $this->dispatchThroughMiddleware($job);

        $this->assertFalse(Tenancy::check());
    }

    public function test_constructing_a_job_without_a_context_throws(): void
    {
        $this->expectException(TenantContextMissingException::class);

        new RecordCurrentAccountJob;
    }

    private function dispatchThroughMiddleware(RecordCurrentAccountJob $job): void
    {
        $pipeline = array_reduce(
            array_reverse($job->middleware()),
            fn (callable $next, object $middleware): callable => fn () => $middleware->handle(
                $job,
                fn () => $next(),
            ),
            fn () => $job->handle(),
        );

        $pipeline();
    }
}
