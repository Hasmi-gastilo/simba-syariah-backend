<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->string('akad_type'); // WADIAH, MUDHARABAH
            $table->decimal('minimum_balance', 15, 2)->default(10000);
            $table->decimal('initial_deposit', 15, 2)->default(50000);
            $table->decimal('nisbah_customer', 5, 2)->nullable();
            $table->decimal('nisbah_bank', 5, 2)->nullable();
            $table->json('tenor_options')->nullable(); // Untuk deposito: [1, 3, 6, 12] bulan
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('savings_product_id')->constrained('savings_products')->cascadeOnDelete();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('status')->default('active'); // active, dormant, frozen, closed
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('savings_products');
    }
};

