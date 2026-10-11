<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LedgerAudit
{
    public function inspect(): array
    {
        return DB::transaction(function () {
            $transactions = DB::table('ledger_transactions')->leftJoin('ledger_entries', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')->groupBy('ledger_transactions.id')->selectRaw('ledger_transactions.id, COUNT(ledger_entries.id) AS entries, COALESCE(SUM(amount_minor),0) AS total')->get();
            $accounts = DB::table('ledger_accounts')->leftJoin('ledger_entries', 'ledger_entries.ledger_account_id', '=', 'ledger_accounts.id')->whereNotNull('wallet_id')->groupBy('ledger_accounts.id', 'ledger_accounts.wallet_id', 'ledger_accounts.code')->selectRaw('ledger_accounts.wallet_id, ledger_accounts.code, COALESCE(SUM(amount_minor),0) AS total')->get();
            $open = DB::table('trades')->where('status', 'open')->groupBy('user_id')->selectRaw('user_id,SUM(stake_minor) AS total')->pluck('total', 'user_id');
            $withdrawals = DB::table('withdrawal_requests')->whereIn('status', ['pending', 'under_review'])->groupBy('user_id')->selectRaw('user_id,SUM(amount_minor) AS total')->pluck('total', 'user_id');
            $wallets = DB::table('wallets')->pluck('user_id', 'id');
            $result = ['unbalanced' => 0, 'empty_transactions' => 0, 'negative_wallet_accounts' => 0, 'reservation_mismatches' => 0];
            foreach ($transactions as $row) {
                if ((int) $row->total !== 0) {
                    $result['unbalanced']++;
                }if ((int) $row->entries < 2) {
                    $result['empty_transactions']++;
                }
            }
            foreach ($accounts as $row) {
                if ((int) $row->total < 0) {
                    $result['negative_wallet_accounts']++;
                }
                if (str_ends_with($row->code, ':reserved')) {
                    $user = $wallets[$row->wallet_id];
                    if ((int) $row->total !== (int) ($open[$user] ?? 0) + (int) ($withdrawals[$user] ?? 0)) {
                        $result['reservation_mismatches']++;
                    }
                }
            }

            return $result;
        });
    }
}
