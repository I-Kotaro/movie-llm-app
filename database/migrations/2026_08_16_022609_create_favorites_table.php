<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            // ユーザーテーブルと紐付ける外部キー（userが削除されたらお気に入りも消える設定）
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // TMDBの映画ID（整数）
            $table->integer('movie_id');
            // 映画のタイトル（毎回APIを叩かなくても表示できるように保存）
            $table->string('movie_title');
            // 映画のポスター画像のURL（同上）
            $table->string('movie_poster_path')->nullable();
            
            $table->timestamps();

            // 同じユーザーが同じ映画を複数回お気に入り登録できないようにする制約
            $table->unique(['user_id', 'movie_id']);
        });
    }

};
