<?php

namespace App\Services\Knowledge\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Files document sections into the existing folder tree. Prefers existing
 * folders; may propose a new subfolder only where nothing fits.
 */
#[Strict]
class TopicAssigner implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You file sections of a company document into the folders of a knowledge base.

        You get the folder tree (each folder with its id) and a list of sections (each with its
        id, heading path and summary). For every section choose 1 to 3 folders, as specific as
        possible (prefer the deepest folder that fits), by id. Use existing folders whenever one
        fits reasonably.

        Only when no folder fits at all, propose a new folder under an existing parent folder
        (by parent id) in new_folders, with a short name in the language of the documents and a
        one-sentence description, and refer to it from the section by its name in
        new_folder_names. Never propose a new top-level folder. Sections with no real content
        (only a title) get no folders.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'assignments' => $schema->array()->items($schema->object([
                'section_id' => $schema->integer()->required(),
                'folder_ids' => $schema->array()->items($schema->integer())->required(),
                'new_folder_names' => $schema->array()->items($schema->string())->required(),
            ])->withoutAdditionalProperties())->required(),
            'new_folders' => $schema->array()->items($schema->object([
                'parent_id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
                'description' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
