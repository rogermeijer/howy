<?php

namespace Tests\Feature\Tenancy;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Pins the sanctioned request-path uses of withoutTenancy().
 *
 * 1. Gmail's Pub/Sub push has no session, so the webhook must look across
 *    accounts to learn which account owns the pushed address.
 * 2. The admin AI log (/admin/ai, super-admins only) reports AI usage across
 *    every account. It only reads, maps to arrays inside the bypass, and loads
 *    a single call's payload back inside Tenancy::for() of its own account.
 *
 * Both are written up in CLAUDE.md rule 5. This test keeps them from spreading:
 * any other call in app/ fails here. Migrations, commands and tests live
 * outside app/ or must be added deliberately, with a reason, to the list below.
 */
class WithoutTenancyGuardTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const array ALLOWED = [
        'Http/Controllers/Webhooks/GmailWebhookController.php',
        'Services/Admin/AiUsageLog.php',
    ];

    public function test_without_tenancy_is_only_called_where_sanctioned(): void
    {
        $callers = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            if ($this->callsWithoutTenancy($file->getContents())) {
                $callers[] = str_replace('\\', '/', $file->getRelativePathname());
            }
        }

        sort($callers);

        $this->assertSame(self::ALLOWED, $callers);
    }

    /**
     * Tokens rather than text, so docblocks and exception messages that mention
     * the method do not count as calls.
     */
    private function callsWithoutTenancy(string $code): bool
    {
        $tokens = array_values(array_filter(
            token_get_all($code),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        foreach ($tokens as $i => $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] === 'withoutTenancy'
                && is_array($tokens[$i - 1] ?? null)
                && in_array($tokens[$i - 1][0], [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                return true;
            }
        }

        return false;
    }
}
