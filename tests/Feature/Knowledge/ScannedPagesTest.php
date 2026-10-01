<?php

namespace Tests\Feature\Knowledge;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\User;
use App\Services\Knowledge\Ai\Agents\ScanReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesKnowledgeAi;
use Tests\TestCase;

/**
 * Pages without a text layer are cut out with qpdf and read by the LLM.
 */
class ScannedPagesTest extends TestCase
{
    use FakesKnowledgeAi, RefreshDatabase;

    public function test_a_scanned_page_is_read_by_the_llm_and_slotted_in_at_its_page(): void
    {
        Storage::fake('knowledge');
        $this->fakeKnowledgeAi();

        ScanReader::fake([[
            'pages' => [['page' => 2, 'markdown' => "## 2 Gereedschap\nGereedschap gaat na gebruik terug in de bus."]],
        ]])->preventStrayPrompts();

        Process::fake([
            '*pdfinfo*' => Process::result("Pages: 2\n"),
            '*pdftohtml*' => Process::result(<<<'XML'
            <?xml version="1.0"?>
            <pdf2xml>
            <fontspec id="0" size="12" family="Times" color="#000"/>
            <fontspec id="1" size="20" family="Times" color="#000"/>
            <page number="1" height="1000" width="800">
                <text top="100" left="100" width="300" height="20" font="1">1 Werktijden</text>
                <text top="150" left="100" width="500" height="12" font="0">De werkweek is 38 uur, van maandag tot en met vrijdag.</text>
            </page>
            <page number="2" height="1000" width="800"></page>
            </pdf2xml>
            XML),
            '*qpdf*' => Process::result(),
        ]);

        $account = Account::factory()->create();
        $admin = User::factory()->withAccount($account)->create();

        $this->actingAs($admin)->post(route('knowledge.documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('scan.pdf', '%PDF-1.4 scan'),
            'type' => 'manual',
            'is_core' => false,
            'language' => 'nl',
        ])->assertSessionHasNoErrors();

        Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), '--pages'));
        ScanReader::assertPrompted(fn ($prompt) => $prompt->contains('in this order: 2') && $prompt->attachments->count() === 1);

        Tenancy::for($account, function () {
            $section = DocumentSection::query()->where('heading', '2 Gereedschap')->sole();
            $this->assertSame(2, $section->page_from);
            $this->assertSame([2], DocumentProcessingStep::query()->where('step', 'extract')->sole()->meta['scanned_pages'] ?? null);
        });
    }
}
