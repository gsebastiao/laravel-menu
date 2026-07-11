<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('dynamic-menu.table', 'menu_items');

        Schema::create($table, function (Blueprint $table) {
            $table->id();

            // Hierarquia (N níveis). Sem FK rígida para permitir remoção livre;
            // a integridade da árvore é tratada pela aplicação/cascata lógica.
            $table->unsignedBigInteger('parent_id')->nullable()->index();

            // Identidade e apresentação
            $table->string('name')->comment('Identificador interno/máquina, ex.: users.index');
            $table->string('label')->comment('Texto exibido ao utilizador');
            $table->string('description')->nullable();
            $table->string('route')->nullable()->comment('Nome de rota, URL ou caminho');
            $table->string('icon')->nullable();

            // Permissão flexível: uma única coluna.
            // - modo 'none'   -> ignorada
            // - modo 'string' -> guarda a permissão como texto (ex.: user.create)
            // - modo 'id'     -> guarda o id (como texto) que aponta para a
            //                    tabela definida em config('dynamic-menu.resolver')
            $table->string('permission')->nullable()->index();

            // Comportamento e ordenação
            $table->integer('order')->default(0)->index();
            $table->string('target')->nullable()->comment('_self, _blank, etc.');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_separator')->default(false);

            // Extras
            $table->string('badge')->nullable()->comment('Texto/valor de badge, ex.: "novo" ou contador');
            $table->json('params')->nullable()->comment('Parâmetros de rota ou metadados arbitrários');

            $table->timestamps();
            $table->softDeletes();

            // Índice composto útil para montar a árvore ordenada por nível.
            $table->index(['parent_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('dynamic-menu.table', 'menu_items'));
    }
};
