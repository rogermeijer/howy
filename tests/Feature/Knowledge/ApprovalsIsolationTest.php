<?php

namespace Tests\Feature\Knowledge;

use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Another account's waiting documents and mail are neither listed nor
 * counted in the approvals.
 */
class ApprovalsIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_our_own_waiting_material_is_listed_and_counted(): void
    {
        $ours = Account::factory()->create();
        $theirs = Account::factory()->create();
        $admin = User::factory()->withAccount($ours)->create();

        $mine = Tenancy::for($ours, fn () => DocumentVersion::factory()->awaitingReview()->create());

        Tenancy::for($theirs, function (): void {
            DocumentVersion::factory()->count(2)->awaitingReview()->create();
            $email = Email::factory()->create(['mailbox_id' => Mailbox::factory()->create()->id]);
            EmailInterpretation::factory()->create([
                'email_id' => $email->id,
                'status' => InterpretationStatus::Done,
                'outcome' => InterpretationOutcome::Added,
                'needs_review' => true,
                'statements' => [],
            ]);
        });

        $this->actingAs($admin)
            ->get(route('knowledge.approvals'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 1)
                ->where('documents.0.versionId', $mine->id)
                ->has('mails', 0)
                ->where('knowledge.awaitingApproval', 1));
    }
}
