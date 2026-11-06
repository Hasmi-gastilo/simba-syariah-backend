<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financing_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->string('akad_type'); // MURABAHAH, MUDHARABAH, MUSYARAKAH, IJARAH
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('financings', function (Blueprint $table) {
            $table->id();
            $table->string('financing_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('financing_product_id')->constrained('financing_products')->cascadeOnDelete();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('margin_amount', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->string('profit_sharing_ratio')->nullable(); // Nisbah misal 60:40
            $table->integer('tenor'); // Durasi bulan
            $table->decimal('installment_amount', 15, 2)->default(0);
            $table->text('purpose')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED, ACTIVE, COMPLETED
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financing_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_id')->constrained('financings')->cascadeOnDelete();
            $table->string('document_name');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('financing_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_id')->constrained('financings')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('status')->default('UNPAID'); // UNPAID, PARTIAL, PAID
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_installments');
        Schema::dropIfExists('financing_documents');
        Schema::dropIfExists('financings');
        Schema::dropIfExists('financing_products');
    }
};

