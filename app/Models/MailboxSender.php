<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\SenderAccess;
use Database\Factories\MailboxSenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A sender listed on a mailbox, and what Howy may do with their mail: an
 * address (jan@klant.nl), or a whole domain (klant.nl) when the entry has no @.
 * An address beats its domain; see Mailbox::access().
 *
 * @property int $id
 * @property int $account_id
 * @property int $mailbox_id
 * @property string $entry
 * @property SenderAccess $access
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Mailbox $mailbox
 */
#[Fillable(['mailbox_id', 'entry', 'access'])]
class MailboxSender extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<MailboxSenderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access' => SenderAccess::class,
        ];
    }
}
