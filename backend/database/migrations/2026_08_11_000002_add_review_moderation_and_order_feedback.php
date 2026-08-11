<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_pkey');

        Schema::table('reviews', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
            $table->foreignId('order_product_id')->nullable()
                ->constrained('order_products')->nullOnDelete();
            $table->string('status', 16)->default('published');
            $table->timestamp('customer_edited_at')->nullable();
            $table->unique(['user_id', 'product_id'], 'reviews_user_product_unique');
            $table->unique('order_product_id', 'reviews_order_product_unique');
            $table->index(
                ['product_id', 'status', 'created_at'],
                'reviews_public_listing_index'
            );
        });

        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range_check CHECK (rating BETWEEN 1 AND 5)');
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_status_check CHECK (status IN ('published', 'hidden'))");

        Schema::create('review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->unique()
                ->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()
                ->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('delivery_rating')->nullable();
            $table->unsignedTinyInteger('packing_rating')->nullable();
            $table->unsignedTinyInteger('service_rating')->nullable();
            $table->text('comment')->nullable();
            $table->string('status', 16)->default('published');
            $table->timestamp('customer_edited_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'order_feedback_moderation_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE order_feedback
            ADD CONSTRAINT order_feedback_ratings_check
            CHECK (
                (delivery_rating IS NULL OR delivery_rating BETWEEN 1 AND 5)
                AND (packing_rating IS NULL OR packing_rating BETWEEN 1 AND 5)
                AND (service_rating IS NULL OR service_rating BETWEEN 1 AND 5)
                AND (
                    delivery_rating IS NOT NULL
                    OR packing_rating IS NOT NULL
                    OR service_rating IS NOT NULL
                )
            )
        SQL);
        DB::statement("ALTER TABLE order_feedback ADD CONSTRAINT order_feedback_status_check CHECK (status IN ('published', 'hidden'))");

        Schema::create('content_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->nullable()
                ->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('order_feedback_id')->nullable()
                ->constrained('order_feedback')->cascadeOnDelete();
            $table->foreignId('moderator_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('moderator_name');
            $table->string('action', 16);
            $table->string('reason_code', 32)->nullable();
            $table->text('comment');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['review_id', 'created_at'], 'content_moderation_review_index');
            $table->index(['order_feedback_id', 'created_at'], 'content_moderation_feedback_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE content_moderation_logs
            ADD CONSTRAINT content_moderation_target_check
            CHECK (
                (review_id IS NOT NULL AND order_feedback_id IS NULL)
                OR (review_id IS NULL AND order_feedback_id IS NOT NULL)
            )
        SQL);
        DB::statement("ALTER TABLE content_moderation_logs ADD CONSTRAINT content_moderation_action_check CHECK (action IN ('hide', 'restore'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('content_moderation_logs');
        Schema::dropIfExists('order_feedback');
        Schema::dropIfExists('review_replies');

        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_rating_range_check');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_status_check');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_public_listing_index');
            $table->dropUnique('reviews_order_product_unique');
            $table->dropUnique('reviews_user_product_unique');
            $table->dropConstrainedForeignId('order_product_id');
            $table->dropColumn(['status', 'customer_edited_at']);
        });

        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_pkey');
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_pkey PRIMARY KEY (product_id, user_id)');
    }
};
