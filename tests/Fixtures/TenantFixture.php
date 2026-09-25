<?php

namespace Tests\Fixtures;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stand-in tenant-scoped model for the tenancy tests.
 *
 * The application has no account-owned model yet — Account is the tenant itself
 * and roles are enums — so without this there would be nothing to prove the trait
 * against. Its table is created inside each test and rolled back with the
 * surrounding transaction, so the schema guard never sees it.
 *
 * @property int $id
 * @property int $account_id
 * @property string $name
 */
#[Fillable(['name'])]
class TenantFixture extends Model
{
    use BelongsToAccount;

    protected $table = 'tenant_fixtures';

    public static function migrate(): void
    {
        Schema::dropIfExists('tenant_fixtures');

        Schema::create('tenant_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }
}
