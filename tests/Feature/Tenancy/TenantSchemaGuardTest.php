<?php

namespace Tests\Feature\Tenancy;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The guard that keeps multi-tenancy from eroding.
 *
 * Driven off the migrated schema rather than a hand-maintained list of models,
 * because reflection alone cannot notice a migration that adds account_id to a
 * table whose model someone forgot to update — which is exactly the mistake worth
 * catching.
 */
class TenantSchemaGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The only tables allowed to carry account_id without being tenant-scoped.
     *
     * account_user resolves which accounts a user belongs to. Scoping it to the
     * current account would make switching impossible, because you could only read
     * memberships for the account you are already in.
     *
     * Adding an entry here needs a written reason here and in CLAUDE.md.
     *
     * @var list<string>
     */
    private const array UNSCOPED_TENANT_TABLES = ['account_user'];

    public function test_every_table_with_an_account_id_column_has_a_scoped_model(): void
    {
        $models = $this->modelsByTable();
        $tables = $this->tablesWithAccountId();

        // Guard the guard: if this ever comes back empty the schema scan has broken
        // and the loop below would pass without checking anything.
        $this->assertNotEmpty($tables, 'No tenant tables found — the schema scan is broken.');

        foreach ($tables as $table) {
            if (in_array($table, self::UNSCOPED_TENANT_TABLES, true)) {
                continue;
            }

            $this->assertArrayHasKey(
                $table,
                $models,
                "Table [{$table}] has an account_id column but no model in app/Models maps to it.",
            );

            // class_uses, not class_uses_recursive: #[ScopedBy] is only resolved
            // from directly-used traits, so nesting BelongsToAccount inside another
            // trait would silently lose the global scope.
            $this->assertContains(
                BelongsToAccount::class,
                class_uses($models[$table]),
                "Model [{$models[$table]}] is backed by tenant table [{$table}] but does not "
                .'`use BelongsToAccount;` directly on the class.',
            );
        }
    }

    public function test_every_model_using_the_trait_is_backed_by_a_tenant_table(): void
    {
        $models = $this->modelsByTable();

        $this->assertNotEmpty($models, 'No models discovered — the model scan is broken.');

        foreach ($models as $table => $model) {
            if (! in_array(BelongsToAccount::class, class_uses($model), true)) {
                continue;
            }

            $this->assertContains(
                'account_id',
                Schema::getColumnListing($table),
                "Model [{$model}] uses BelongsToAccount but table [{$table}] has no account_id column.",
            );
        }
    }

    public function test_application_code_never_removes_a_global_scope(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            if (Str::contains($file->getContents(), ['withoutGlobalScope', 'withoutGlobalScopes'])) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Removing a global scope in app/ bypasses account isolation. Use Tenancy::withoutTenancy(), '
            .'and only outside a request path.',
        );
    }

    /**
     * Proves the guard actually detects a violation rather than passing vacuously.
     *
     * A table with an account_id column and no model behind it is the exact mistake
     * the first test exists to catch, so assert here that the detection sees it.
     */
    public function test_the_guard_detects_a_tenant_table_that_has_no_model(): void
    {
        Schema::create('guard_probe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
        });

        $this->assertContains('guard_probe', $this->tablesWithAccountId());
        $this->assertArrayNotHasKey('guard_probe', $this->modelsByTable());

        Schema::drop('guard_probe');
    }

    /**
     * @return list<string>
     */
    private function tablesWithAccountId(): array
    {
        // schemaQualified: false — the default returns names like "cc.accounts",
        // which would never match Model::getTable().
        return collect(Schema::getTableListing(schemaQualified: false))
            ->filter(fn (string $table): bool => in_array('account_id', Schema::getColumnListing($table), true))
            ->values()
            ->all();
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function modelsByTable(): array
    {
        $models = [];

        foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
            /** @var class-string $class */
            $class = 'App\\Models\\'.Str::of($file->getRelativePathname())
                ->replace(['/', '.php'], ['\\', ''])
                ->toString();

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            /** @var Model $instance */
            $instance = $reflection->newInstance();

            $models[$instance->getTable()] = $class;
        }

        return $models;
    }
}
