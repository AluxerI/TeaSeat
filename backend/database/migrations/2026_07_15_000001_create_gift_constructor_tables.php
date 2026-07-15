<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 32)->default('regular')->after('price');
            $table->boolean('is_individual_sale_enabled')->default(true)->after('product_type');
            $table->text('assembly_instructions')->nullable()->after('description');
            $table->index(['product_type', 'is_available']);
            $table->index(['is_individual_sale_enabled', 'is_available'], 'products_direct_sale_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE products
            ADD CONSTRAINT products_type_check
            CHECK (product_type IN ('regular', 'preassembled_gift'))
        SQL);

        Schema::create('gift_size_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 16);
            $table->unsignedSmallInteger('width_cells');
            $table->unsignedSmallInteger('height_cells');
            $table->boolean('can_rotate')->default(true);
            $table->unsignedInteger('max_weight_grams')->nullable();
            $table->decimal('default_markup_amount', 10, 2)->default(0);
            $table->boolean('simple_constructor_enabled')->default(false);
            $table->unsignedSmallInteger('simple_tea_count')->nullable();
            $table->unsignedSmallInteger('simple_sweet_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });
        DB::statement("ALTER TABLE gift_size_profiles ADD CONSTRAINT gift_size_profiles_kind_check CHECK (kind IN ('item', 'box'))");
        DB::statement('ALTER TABLE gift_size_profiles ADD CONSTRAINT gift_size_profiles_dimensions_check CHECK (width_cells > 0 AND height_cells > 0)');
        DB::statement('ALTER TABLE gift_size_profiles ADD CONSTRAINT gift_size_profiles_markup_check CHECK (default_markup_amount >= 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE gift_size_profiles
            ADD CONSTRAINT gift_size_profiles_simple_constructor_check
            CHECK (
                simple_constructor_enabled = FALSE
                OR (
                    kind = 'box'
                    AND simple_tea_count > 0
                    AND simple_sweet_count > 0
                )
            )
        SQL);

        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gift_size_profile_id')->constrained('gift_size_profiles')->restrictOnDelete();
            $table->string('label');
            $table->unsignedInteger('product_quantity');
            $table->string('constructor_role', 16)->default('general');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'product_quantity']);
            $table->index(['constructor_role', 'is_active']);
        });
        DB::statement("ALTER TABLE product_sizes ADD CONSTRAINT product_sizes_role_check CHECK (constructor_role IN ('tea', 'sweet', 'general'))");
        DB::statement('ALTER TABLE product_sizes ADD CONSTRAINT product_sizes_quantity_check CHECK (product_quantity > 0)');

        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gift_size_profile_id')->constrained('gift_size_profiles')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('visibility', 16)->default('private');
            $table->decimal('markup_amount', 10, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->json('layout_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
        DB::statement("ALTER TABLE gifts ADD CONSTRAINT gifts_status_check CHECK (status IN ('draft', 'active', 'archived'))");
        DB::statement("ALTER TABLE gifts ADD CONSTRAINT gifts_visibility_check CHECK (visibility IN ('private'))");
        DB::statement('ALTER TABLE gifts ADD CONSTRAINT gifts_markup_check CHECK (markup_amount >= 0)');

        Schema::create('gift_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained('product_sizes')->restrictOnDelete();
            $table->uuid('client_item_id');
            $table->unsignedSmallInteger('position_x');
            $table->unsignedSmallInteger('position_y');
            $table->boolean('is_rotated')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['gift_id', 'client_item_id']);
            $table->index(['gift_id', 'sort_order']);
        });

        Schema::create('order_gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gift_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('client_instance_id');
            $table->unsignedInteger('gift_version');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('markup_unit_amount', 10, 2)->default(0);
            $table->decimal('markup_total_amount', 10, 2)->default(0);
            $table->decimal('components_base_total', 10, 2)->default(0);
            $table->decimal('components_discount_amount', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->json('layout_snapshot');
            $table->timestamps();

            $table->unique(['order_id', 'client_instance_id']);
            $table->index(['order_id', 'gift_id']);
        });
        DB::statement('ALTER TABLE order_gifts ADD CONSTRAINT order_gifts_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_gifts ADD CONSTRAINT order_gifts_money_check CHECK (markup_unit_amount >= 0 AND markup_total_amount >= 0 AND components_base_total >= 0 AND components_discount_amount >= 0 AND total_price >= 0)');

        Schema::table('order_products', function (Blueprint $table) {
            $table->foreignId('order_gift_id')->nullable()->after('product_id')->constrained('order_gifts')->cascadeOnDelete();
            $table->foreignId('product_size_id')->nullable()->after('order_gift_id')->constrained('product_sizes')->nullOnDelete();
            $table->uuid('gift_item_client_id')->nullable()->after('product_size_id');
            $table->unsignedInteger('gift_item_quantity')->nullable()->after('gift_item_client_id');
            $table->unsignedSmallInteger('gift_item_sort_order')->nullable()->after('gift_item_quantity');
            $table->index(['order_gift_id', 'gift_item_sort_order'], 'order_products_gift_group_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            DROP CONSTRAINT IF EXISTS inventory_movements_type_check
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_type_check
            CHECK (type IN (
                'opening_balance', 'online_reserve', 'online_release', 'online_sale', 'online_return',
                'seller_reserve', 'seller_release', 'seller_sale', 'restock', 'adjustment',
                'transfer_in', 'transfer_out', 'gift_assembly_consume', 'gift_assembly_produce'
            ))
        SQL);
    }

    public function down(): void
    {
        DB::statement("UPDATE inventory_movements SET type = 'adjustment' WHERE type IN ('gift_assembly_consume', 'gift_assembly_produce')");
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT IF EXISTS inventory_movements_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_type_check
            CHECK (type IN (
                'opening_balance', 'online_reserve', 'online_release', 'online_sale', 'online_return',
                'seller_reserve', 'seller_release', 'seller_sale', 'restock', 'adjustment',
                'transfer_in', 'transfer_out'
            ))
        SQL);

        Schema::table('order_products', function (Blueprint $table) {
            $table->dropIndex('order_products_gift_group_index');
            $table->dropConstrainedForeignId('product_size_id');
            $table->dropConstrainedForeignId('order_gift_id');
            $table->dropColumn(['gift_item_client_id', 'gift_item_quantity', 'gift_item_sort_order']);
        });

        Schema::dropIfExists('order_gifts');
        Schema::dropIfExists('gift_items');
        Schema::dropIfExists('gifts');
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('gift_size_profiles');

        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_type_check');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_type', 'is_available']);
            $table->dropIndex('products_direct_sale_index');
            $table->dropColumn(['product_type', 'is_individual_sale_enabled', 'assembly_instructions']);
        });
    }
};
