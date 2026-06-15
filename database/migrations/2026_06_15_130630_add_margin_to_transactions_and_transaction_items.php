<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('margin', 12, 2)->default(0)->after('total_amount');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->decimal('margin', 12, 2)->default(0)->after('subtotal');
        });

        // Backfill historical data
        try {
            $items = DB::table('transaction_items')->get();
            foreach ($items as $item) {
                $product = DB::table('products')->where('id', $item->product_id)->first();
                $buyPrice = $product ? $product->buy_price : 0;
                $margin = ($item->price_at_transaction - $buyPrice) * $item->quantity;
                
                DB::table('transaction_items')->where('id', $item->id)->update([
                    'margin' => $margin
                ]);
            }

            $transactions = DB::table('transactions')->get();
            foreach ($transactions as $transaction) {
                $totalMargin = DB::table('transaction_items')
                    ->where('transaction_id', $transaction->id)
                    ->sum('margin');
                
                DB::table('transactions')->where('id', $transaction->id)->update([
                    'margin' => $totalMargin
                ]);
            }
        } catch (\Exception $e) {
            // Log or handle error, but don't crash the migration if database is clean
            logger('Failed to backfill historical margins: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('margin');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('margin');
        });
    }
};
