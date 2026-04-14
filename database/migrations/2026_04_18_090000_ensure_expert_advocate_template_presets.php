<?php

use Database\Seeders\TemplatePresetSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * На существующих БД раньше сидер создавал только moto-rental и выходил по early return —
 * expert/advocate пресеты не появлялись без ручного вмешательства.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new TemplatePresetSeeder)->run();
    }
};
