<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', fn (Blueprint $t) => $t->index(['support_conversation_id', 'id'], 'support_history_index'));
        Schema::table('kyc_applications', fn (Blueprint $t) => $t->index(['user_id', 'id'], 'kyc_history_index'));
        Schema::table('ledger_transactions', fn (Blueprint $t) => $t->index(['user_id', 'id'], 'ledger_history_index'));
    }

    public function down(): void
    {
        Schema::table('support_messages', fn (Blueprint $t) => $t->dropIndex('support_history_index'));
        Schema::table('kyc_applications', fn (Blueprint $t) => $t->dropIndex('kyc_history_index'));
        Schema::table('ledger_transactions', fn (Blueprint $t) => $t->dropIndex('ledger_history_index'));
    }
};
