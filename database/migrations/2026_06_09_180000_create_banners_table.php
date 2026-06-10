<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('Служебное имя для админки');
            $table->string('image_path')->nullable()->comment('Путь к картинке в storage/app/public');
            $table->string('placeholder_color', 255)->nullable()->comment('CSS-цвет или градиент для заглушки');
            $table->string('placeholder_text')->nullable()->comment('Текст заглушки');
            $table->string('link_url', 500)->nullable()->comment('Куда ведёт тап');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
