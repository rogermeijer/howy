<?php

namespace Tests\Feature\Knowledge;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Document;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeFactSource;
use App\Models\User;
use App\Services\Knowledge\Facts\FactSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where another account's facts are stated is neither visible nor touched
 * from ours: not when listing sources, not when a document of ours goes.
 */
class FactSourcesIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_accounts_sources_stay_out_of_sight_and_untouched(): void
    {
        $ours = Account::factory()->create();
        $theirs = Account::factory()->create();
        User::factory()->withAccount($ours)->create();

        $mine = Tenancy::for($ours, fn () => KnowledgeFact::factory()->create());
        $foreign = Tenancy::for($theirs, fn () => KnowledgeFact::factory()->count(2)->create()->first());

        Tenancy::for($ours, function () use ($mine) {
            $this->assertSame([$mine->id], KnowledgeFactSource::query()->pluck('knowledge_fact_id')->all());
            $this->assertNull(KnowledgeFactSource::query()->where('knowledge_fact_id', '!=', $mine->id)->first());

            app(FactSources::class)->release(Document::query()->sole(), keepFacts: false);
        });

        Tenancy::for($theirs, function () use ($foreign) {
            $this->assertSame(2, KnowledgeFact::query()->count());
            $this->assertSame(2, KnowledgeFactSource::query()->count());
            $this->assertTrue($foreign->sources()->exists());
        });
    }
}
