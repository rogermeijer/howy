<?php

use App\Enums\SourceKind;
use App\Facades\Tenancy;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a file came from, every time it came: uploaded by someone, or
     * shared in a mail thread. A file sent again is not processed again; the
     * thread it came in is added here, so the document links back to every
     * conversation it was part of.
     */
    public function up(): void
    {
        Schema::create('document_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_id')->nullable()->constrained()->nullOnDelete();
            // The file name as it was sent this time.
            $table->string('filename');
            // What the mail said about the file.
            $table->text('context')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'document_version_id', 'email_id']);
            $table->index(['account_id', 'email_id']);
        });

        // Every account's rows, on purpose: a migration has no tenant. What is
        // there was uploaded.
        Tenancy::withoutTenancy(fn () => DocumentVersion::query()->eachById(function (DocumentVersion $version): void {
            (new DocumentSource)->forceFill([
                'account_id' => $version->account_id,
                'document_id' => $version->document_id,
                'document_version_id' => $version->id,
                'kind' => SourceKind::Upload,
                'user_id' => $version->uploaded_by_user_id,
                'filename' => $version->original_filename,
                'created_at' => $version->created_at,
                'updated_at' => $version->created_at,
            ])->save();
        }));
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sources');
    }
};
