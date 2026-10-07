<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Mint private beta invite codes to hand out.
 */
#[Signature('vouchers:create {--count=1 : How many codes} {--uses=1 : Registrations each code allows} {--expires= : Last valid day (YYYY-MM-DD)} {--note= : Who or what the codes are for}')]
#[Description('Create private beta invite codes')]
class CreateVouchers extends Command
{
    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        $uses = max(1, (int) $this->option('uses'));
        $expires = $this->option('expires') ? CarbonImmutable::parse((string) $this->option('expires'))->endOfDay() : null;
        $note = $this->option('note') ? (string) $this->option('note') : null;

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $voucher = Voucher::query()->create([
                'code' => Voucher::generateCode(),
                'note' => $note,
                'max_uses' => $uses,
                'expires_at' => $expires,
            ]);

            $rows[] = [$voucher->code, $uses, $expires?->toDateString() ?? '—', $note ?? ''];
        }

        $this->table(['Code', 'Uses', 'Expires', 'Note'], $rows);

        return self::SUCCESS;
    }
}
