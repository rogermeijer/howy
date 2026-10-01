<?php

namespace App\Services\Knowledge\Seeding;

use App\Facades\Tenancy;
use App\Models\AiUsageRecord;
use App\Models\Document;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeSummary;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A processed knowledge base as a file: everything the AI produced (sections,
 * chunks with context lines and embeddings, summaries, facts, folders) plus
 * the originals, so a fresh database can have it back without a single AI
 * call. Ids are remapped on import; vectors travel as packed float32.
 *
 * Works on the current account: call inside Tenancy::for().
 */
class KnowledgeSnapshot
{
    public const int FORMAT = 1;

    /**
     * Write the current account's knowledge base to $directory/$file.json.gz,
     * labelled with the account's name so a seeder can find it again.
     *
     * @return array<string, int> counts per kind
     */
    public function export(string $directory, string $file, string $account): array
    {
        $files = $directory.'/files';
        @mkdir($files, 0755, true);

        $emails = User::query()->pluck('email', 'id');

        $versions = DocumentVersion::query()->orderBy('id')->get()->map(function (DocumentVersion $version) use ($files, $emails): array {
            $extension = pathinfo($version->path, PATHINFO_EXTENSION);
            $disk = Storage::disk($version->disk);

            file_put_contents("{$files}/{$version->sha256}.{$extension}", $disk->get($version->path));

            if ($version->extracted_path !== null && $disk->exists($version->extracted_path)) {
                file_put_contents("{$files}/{$version->sha256}.extracted.json", $disk->get($version->extracted_path));
            }

            return [
                ...$this->raw($version, ['account_id', 'disk', 'path', 'extracted_path', 'uploaded_by_user_id']),
                'uploaded_by' => $emails[$version->uploaded_by_user_id] ?? null,
                'file' => "{$version->sha256}.{$extension}",
                'extracted' => $version->extracted_path === null ? null : "{$version->sha256}.extracted.json",
            ];
        })->all();

        $data = [
            'format' => self::FORMAT,
            'account' => $account,
            'embedding_model' => config('knowledge.embeddings.model'),
            'dimensions' => (int) config('knowledge.embeddings.dimensions'),
            'documents' => Document::query()->orderBy('id')->get()->map(fn (Document $document): array => [
                ...$this->raw($document, ['account_id', 'uploaded_by_user_id']),
                'uploaded_by' => $emails[$document->uploaded_by_user_id] ?? null,
            ])->all(),
            'versions' => $versions,
            'steps' => $this->rows(DocumentProcessingStep::query()->orderBy('id')->get()),
            'sections' => $this->rows(DocumentSection::query()->orderBy('id')->get()),
            'chunks' => $this->rows(KnowledgeChunk::query()->orderBy('id')->get(), vectors: true),
            'summaries' => $this->rows(KnowledgeSummary::query()->orderBy('id')->get()),
            'facts' => $this->rows(KnowledgeFact::query()->orderBy('id')->get(), vectors: true),
            'topics' => $this->rows(KnowledgeTopic::query()->orderBy('depth')->orderBy('id')->get(), vectors: true),
            'links' => $this->rows(KnowledgeTopicLink::query()->orderBy('id')->get()),
            'usage' => $this->rows(AiUsageRecord::query()->orderBy('id')->get()),
        ];

        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        file_put_contents("{$directory}/{$file}.json.gz", gzencode($json, 9) ?: throw new RuntimeException('Could not compress the snapshot.'));

        return array_map(count(...), array_filter($data, 'is_array'));
    }

    /**
     * Restore a snapshot into the current account. Ids are new; every
     * reference between rows is remapped. Rows are inserted raw (no casts) so
     * stored values come back exactly, while the model events still fill
     * account_id and rebuild the folder paths.
     *
     * @return array<string, int>
     */
    public function import(string $file, string $disk): array
    {
        $directory = dirname($file).'/files';
        /** @var array<string, mixed> $data */
        $data = json_decode((string) gzdecode((string) file_get_contents($file)), true, flags: JSON_THROW_ON_ERROR);

        if (($data['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException("Unsupported knowledge snapshot format in {$file}.");
        }

        $users = User::query()->pluck('id', 'email');
        /** @var array<string, array<int, int>> $map old id => new id, per kind */
        $map = ['document' => [], 'document_version' => [], 'document_section' => [], 'knowledge_chunk' => [], 'knowledge_fact' => [], 'knowledge_topic' => []];
        // By reference: the map fills up as rows are inserted.
        $ref = function (string $kind, mixed $id) use (&$map): ?int {
            return $id === null ? null : ($map[$kind][(int) $id] ?? null);
        };
        $counts = [];

        foreach ($this->list($data, 'documents') as $row) {
            $document = $this->insert(new Document, [
                ...array_diff_key($row, ['uploaded_by' => true, 'current_version_id' => true]),
                'uploaded_by_user_id' => $users[$row['uploaded_by'] ?? ''] ?? null,
            ]);
            $map['document'][(int) $row['id']] = $document->id;
        }

        foreach ($this->list($data, 'versions') as $row) {
            $documentId = $ref('document', $row['document_id']);
            $folder = 'accounts/'.Tenancy::id()."/documents/{$documentId}/v{$row['version_number']}";
            $path = "{$folder}/{$row['file']}";
            Storage::disk($disk)->put($path, (string) file_get_contents("{$directory}/{$row['file']}"));

            $extracted = null;
            if (is_string($row['extracted'] ?? null) && is_file("{$directory}/{$row['extracted']}")) {
                $extracted = "{$folder}/".pathinfo((string) $row['file'], PATHINFO_FILENAME).'.extracted.json';
                Storage::disk($disk)->put($extracted, (string) file_get_contents("{$directory}/{$row['extracted']}"));
            }

            $version = $this->insert(new DocumentVersion, [
                ...array_diff_key($row, ['uploaded_by' => true, 'file' => true, 'extracted' => true]),
                'document_id' => $documentId,
                'disk' => $disk,
                'path' => $path,
                'extracted_path' => $extracted,
                'uploaded_by_user_id' => $users[$row['uploaded_by'] ?? ''] ?? null,
            ]);
            $map['document_version'][(int) $row['id']] = $version->id;
        }

        foreach ($this->list($data, 'documents') as $row) {
            Document::query()->whereKey($ref('document', $row['id']))->update(['current_version_id' => $ref('document_version', $row['current_version_id'])]);
        }

        foreach ($this->list($data, 'steps') as $row) {
            $this->insert(new DocumentProcessingStep, [...$row, 'document_version_id' => $ref('document_version', $row['document_version_id'])]);
        }

        foreach ($this->list($data, 'sections') as $row) {
            $section = $this->insert(new DocumentSection, [
                ...$row,
                'document_id' => $ref('document', $row['document_id']),
                'document_version_id' => $ref('document_version', $row['document_version_id']),
                'parent_id' => $ref('document_section', $row['parent_id']),
                'previous_section_id' => $ref('document_section', $row['previous_section_id']),
            ]);
            $map['document_section'][(int) $row['id']] = $section->id;
        }

        foreach ($this->list($data, 'chunks') as $row) {
            $chunk = $this->insert(new KnowledgeChunk, [
                ...$this->vector($row),
                'source_id' => $ref((string) $row['source_type'], $row['source_id']),
                'document_id' => $ref('document', $row['document_id']),
                'section_id' => $ref('document_section', $row['section_id']),
            ]);
            $map['knowledge_chunk'][(int) $row['id']] = $chunk->id;
        }

        foreach ($this->list($data, 'facts') as $row) {
            $fact = $this->insert(new KnowledgeFact, [
                ...array_diff_key($this->vector($row), ['superseded_by_id' => true]),
                'source_id' => $ref((string) $row['source_type'], $row['source_id']),
                'document_id' => $ref('document', $row['document_id']),
                'document_version_id' => $ref('document_version', $row['document_version_id']),
                'section_id' => $ref('document_section', $row['section_id']),
                'chunk_id' => $ref('knowledge_chunk', $row['chunk_id']),
            ]);
            $map['knowledge_fact'][(int) $row['id']] = $fact->id;
        }

        foreach ($this->list($data, 'facts') as $row) {
            if ($row['superseded_by_id'] !== null) {
                KnowledgeFact::query()->whereKey($ref('knowledge_fact', $row['id']))->update(['superseded_by_id' => $ref('knowledge_fact', $row['superseded_by_id'])]);
            }
        }

        // Parents first (ordered by depth on export); paths and depths are
        // rebuilt from the new parent ids by the model.
        foreach ($this->list($data, 'topics') as $row) {
            $topic = $this->insert(new KnowledgeTopic, [...$this->vector($row), 'parent_id' => $ref('knowledge_topic', $row['parent_id']), 'path' => '']);
            $map['knowledge_topic'][(int) $row['id']] = $topic->id;
        }

        foreach ($this->list($data, 'summaries') as $row) {
            $id = $ref((string) $row['summarizable_type'], $row['summarizable_id']);

            if ($id !== null) {
                $this->insert(new KnowledgeSummary, [...$row, 'summarizable_id' => $id]);
            }
        }

        foreach ($this->list($data, 'links') as $row) {
            $id = $ref((string) $row['linkable_type'], $row['linkable_id']);
            $topic = $ref('knowledge_topic', $row['topic_id']);

            if ($id !== null && $topic !== null) {
                $this->insert(new KnowledgeTopicLink, [...$row, 'topic_id' => $topic, 'linkable_id' => $id]);
            }
        }

        foreach ($this->list($data, 'usage') as $row) {
            $this->insert(new AiUsageRecord, [
                ...$row,
                'subject_id' => $row['subject_type'] === null ? null : $ref((string) $row['subject_type'], $row['subject_id']),
            ]);
        }

        foreach ($map as $kind => $ids) {
            $counts[$kind] = count($ids);
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function list(array $data, string $key): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = is_array($data[$key] ?? null) ? $data[$key] : [];

        return $rows;
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function insert(Model $model, array $attributes): Model
    {
        unset($attributes['id']);

        $model->setRawAttributes($attributes);
        $model->save();

        return $model;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function vector(array $row): array
    {
        if (is_string($row['embedding'] ?? null)) {
            $row['embedding'] = '['.implode(',', self::unpack($row['embedding'])).']';
        }

        return $row;
    }

    /**
     * @param  iterable<Model>  $models
     * @return list<array<string, mixed>>
     */
    private function rows(iterable $models, bool $vectors = false): array
    {
        $rows = [];

        foreach ($models as $model) {
            $row = $this->raw($model, ['account_id', 'search_vector']);

            if ($vectors && is_string($row['embedding'] ?? null)) {
                $row['embedding'] = self::pack($row['embedding']);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The row as stored, minus what the import derives itself.
     *
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    private function raw(Model $model, array $except): array
    {
        return array_diff_key($model->getAttributes(), array_flip($except));
    }

    /**
     * pgvector text ("[0.1,0.2,…]") to base64 float32: a quarter of the size.
     */
    public static function pack(string $vector): string
    {
        /** @var list<float> $values */
        $values = json_decode($vector, true, flags: JSON_THROW_ON_ERROR);

        return base64_encode(pack('g*', ...$values));
    }

    /**
     * @return list<float>
     */
    public static function unpack(string $packed): array
    {
        $values = unpack('g*', (string) base64_decode($packed, true));

        return $values === false ? [] : array_values(array_map(floatval(...), $values));
    }
}
