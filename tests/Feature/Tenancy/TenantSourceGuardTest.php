<?php

namespace Tests\Feature\Tenancy;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Pins where a request's account comes from.
 *
 * 1. SetTenantContext: the signed-in user's membership.
 * 2. ResolveTenantFromMailLink: a link in one of Howy's mails, opened without
 *    logging in. Its token is made and encrypted by this app alone, so the
 *    account in it is no account id from user input (CLAUDE.md rule 7).
 *
 * Any other Tenancy::set() under app/ fails here. Console work and jobs use
 * Tenancy::for(), which is not this. A third source needs a written reason
 * here and in CLAUDE.md.
 */
class TenantSourceGuardTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const array ALLOWED = [
        'Http/Middleware/ResolveTenantFromMailLink.php',
        'Http/Middleware/SetTenantContext.php',
    ];

    public function test_the_account_is_set_only_where_sanctioned(): void
    {
        $callers = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            if ($this->setsTheAccount($file->getContents())) {
                $callers[] = str_replace('\\', '/', $file->getRelativePathname());
            }
        }

        sort($callers);

        $this->assertSame(self::ALLOWED, $callers);
    }

    /**
     * Tenancy::set( as tokens, so a docblock that names it does not count.
     */
    private function setsTheAccount(string $code): bool
    {
        $tokens = array_values(array_filter(
            token_get_all($code),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        foreach ($tokens as $i => $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] === 'set'
                && is_array($tokens[$i - 1] ?? null) && $tokens[$i - 1][0] === T_DOUBLE_COLON
                && is_array($tokens[$i - 2] ?? null) && $tokens[$i - 2][1] === 'Tenancy') {
                return true;
            }
        }

        return false;
    }
}
