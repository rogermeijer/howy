<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Every private beta invite code and how far it has been used.
 */
#[Signature('vouchers:list {--open : Only codes that can still be redeemed}')]
#[Description('List private beta invite codes')]
class ListVouchers extends Command
{
    public function handle(): int
    {
        $vouchers = Voucher::query()->latest('id')->get()
            ->when($this->option('open'), fn ($vouchers) => $vouchers->filter->isRedeemable());

        $this->table(
            ['Code', 'Used', 'Expires', 'Note', 'Created'],
            $vouchers->map(fn (Voucher $voucher): array => [
                $voucher->code,
                "{$voucher->uses} / {$voucher->max_uses}",
                $voucher->expires_at?->toDateString() ?? '—',
                $voucher->note ?? '',
                $voucher->created_at?->toDateString() ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
