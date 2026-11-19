<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The data step of the plan beside the books (Angel's yes, 2026-11-19):
 *
 *  1. The generated rows nobody confirmed (unreconciled, made by a recurrence or the
 *     tax sync) are soft-deleted — the plan now computes them live. Confirmed ones
 *     stay as history and keep paying their plan items by key.
 *  2. Open one-offs become planned rows.
 *  3. A project's agreed total becomes its one deliverable.
 *  4. Each wallet's imported bank lines go into statements: the lines before the
 *     first one still waiting form a concluded statement (the wallet is concluded up
 *     to the day before that line); the rest form one open statement to finish.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        DB::table('transactions')->whereNull('deleted_at')
            ->whereIn('source', ['recurrence', 'tax'])
            ->where('is_reconciled', false)
            ->update(['deleted_at' => $now]);

        DB::table('transactions')->whereNull('deleted_at')
            ->where('is_one_off', true)
            ->where('is_reconciled', false)
            ->whereNull('bank_line_id')
            ->update(['is_planned' => true]);

        foreach (DB::table('projects')->whereNull('deleted_at')->whereNotNull('agreed_total')->get() as $project) {
            DB::table('project_deliverables')->insert([
                'project_id' => $project->id,
                'name' => $project->name,
                'net' => $project->agreed_total,
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $paired = DB::table('transactions')->whereNull('deleted_at')->whereNotNull('bank_line_id')
            ->distinct()->pluck('bank_line_id')->flip();

        foreach (DB::table('bank_lines')->distinct()->pluck('wallet_id') as $walletId) {
            $lines = DB::table('bank_lines')->where('wallet_id', $walletId)->orderBy('date')->orderBy('position')->get();
            $firstWaiting = $lines->first(fn ($l) => ! $paired->has($l->id));
            $cut = $firstWaiting?->date;
            $wallet = DB::table('wallets')->where('id', $walletId)->first();

            $done = $cut === null ? $lines : $lines->filter(fn ($l) => $l->date < $cut);
            if ($done->isNotEmpty()) {
                $until = $cut === null
                    ? $done->max('date')
                    : Carbon::parse($cut)->subDay()->toDateString();
                $id = DB::table('bank_statements')->insertGetId([
                    'wallet_id' => $walletId,
                    'from_date' => $done->min('date'),
                    'to_date' => $until,
                    'closing_balance' => null,
                    'status' => 'concluded',
                    'concluded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('bank_lines')->whereIn('id', $done->pluck('id'))->update(['bank_statement_id' => $id]);
                DB::table('wallets')->where('id', $walletId)->update(['concluded_until' => $until]);
                DB::table('wallet_conclusions')->insert([
                    'wallet_id' => $walletId,
                    'bank_statement_id' => $id,
                    'until' => $until,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($cut !== null) {
                $rest = $lines->filter(fn ($l) => $l->date >= $cut);
                $to = $rest->max('date');
                $id = DB::table('bank_statements')->insertGetId([
                    'wallet_id' => $walletId,
                    'from_date' => $cut,
                    'to_date' => $to,
                    // The balance the latest import showed, when it is this statement's.
                    'closing_balance' => $wallet !== null && $wallet->statement_date !== null && $wallet->statement_date >= $to
                        ? $wallet->statement_balance
                        : null,
                    'status' => 'open',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('bank_lines')->whereIn('id', $rest->pluck('id'))->update(['bank_statement_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        // Data only; the deleted generated rows can be restored from the backup
        // deploy.sh takes before migrating.
    }
};
