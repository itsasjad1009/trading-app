<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $create = static function (string $name, Closure $callback): void {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        };
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $t) {
                $t->string('phone', 30)->nullable();
                $t->string('role')->default('customer')->index();
                $t->string('status')->default('active');
                $t->string('referral_code', 16)->nullable()->unique();
                $t->text('two_factor_secret')->nullable();
                $t->text('two_factor_recovery_codes')->nullable();
                $t->dateTime('two_factor_confirmed_at')->nullable();
            });
        }
        $create('wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->string('currency')->default('USDT');
            $t->timestamps();
        });
        $create('ledger_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_id')->nullable()->constrained();
            $t->string('code')->unique();
            $t->string('currency')->default('USDT');
            $t->timestamps();
        });
        $create('ledger_transactions', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->string('idempotency_key')->unique();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('type')->index();
            $t->text('description');
            $t->timestamps();
        });
        $create('ledger_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ledger_transaction_id')->constrained();
            $t->foreignId('ledger_account_id')->constrained();
            $t->bigInteger('amount_minor');
            $t->timestamps();
            $t->index(['ledger_account_id', 'id']);
        });
        $create('trading_pairs', function (Blueprint $t) {
            $t->id();
            $t->string('symbol')->unique();
            $t->string('name');
            $t->unsignedBigInteger('base_price_units');
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });
        $create('trades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('trading_pair_id')->constrained();
            $t->uuid('request_key');
            $t->string('request_hash', 64);
            $t->enum('direction', ['buy', 'sell']);
            $t->unsignedBigInteger('stake_minor');
            $t->unsignedBigInteger('entry_price_units');
            $t->unsignedInteger('duration');
            $t->unsignedInteger('payout_bps');
            $t->dateTime('opened_at');
            $t->dateTime('expires_at')->index();
            $t->enum('status', ['open', 'won', 'lost', 'tie'])->default('open')->index();
            $t->timestamps();
            $t->unique(['user_id', 'request_key']);
            $t->index(['user_id', 'status']);
        });
        $create('trade_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trade_id')->unique()->constrained();
            $t->unsignedBigInteger('expiry_price_units');
            $t->bigInteger('profit_minor');
            $t->unsignedBigInteger('payout_minor');
            $t->dateTime('observed_at');
            $t->timestamps();
        });
        foreach (['deposit_requests', 'withdrawal_requests'] as $table) {
            $create($table, function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained();
                $t->uuid('request_key');
                $t->unsignedBigInteger('amount_minor');
                $t->string('status')->default('pending')->index();
                $t->string('method')->default('demo');
                $t->text('destination')->nullable();
                $t->text('review_note')->nullable();
                $t->foreignId('reviewer_id')->nullable()->constrained('users');
                $t->dateTime('reviewed_at')->nullable();
                $t->timestamps();
                $t->unique(['user_id', 'request_key']);
            });
        }
        $create('kyc_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->text('full_name');
            $t->text('national_id');
            $t->text('tax_id')->nullable();
            $t->string('status')->default('pending');
            $t->text('review_note')->nullable();
            $t->dateTime('consented_at');
            $t->foreignId('reviewer_id')->nullable()->constrained('users');
            $t->timestamps();
        });
        $create('kyc_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kyc_application_id')->constrained()->cascadeOnDelete();
            $t->string('side');
            $t->string('path');
            $t->string('mime');
            $t->timestamps();
        });
        $create('support_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->foreignId('agent_id')->nullable()->constrained('users');
            $t->string('status')->default('open');
            $t->timestamps();
        });
        $create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_conversation_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->string('attachment')->nullable();
            $t->dateTime('read_at')->nullable();
            $t->timestamps();
        });
        $create('referrals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_id')->constrained('users');
            $t->foreignId('referred_id')->unique()->constrained('users');
            $t->timestamps();
        });
        $create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        $create('platform_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->timestamps();
        });
        $create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->dateTime('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'platform_settings', 'audit_logs', 'referrals', 'support_messages', 'support_conversations', 'kyc_documents', 'kyc_applications', 'withdrawal_requests', 'deposit_requests', 'trade_settlements', 'trades', 'trading_pairs', 'ledger_entries', 'ledger_transactions', 'ledger_accounts', 'wallets'] as $table) {
            Schema::dropIfExists($table);
        }
        $create = static function (string $name, Closure $callback): void {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        };
        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'role', 'status', 'referral_code', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
        }
    }
};
