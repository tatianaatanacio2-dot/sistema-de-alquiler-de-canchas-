<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRES = [
        1 => 'Chico Chico',
        2 => 'Genry',
        3 => 'El Golazo',
        4 => 'Miky',
        5 => 'Cevichón',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::NOMBRES as $id => $nombre) {
                DB::table('canchas')->where('id', $id)->where('nombre', 'Cancha '.$id)
                    ->update(['nombre' => $nombre]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::NOMBRES as $id => $nombre) {
                DB::table('canchas')->where('id', $id)->where('nombre', $nombre)
                    ->update(['nombre' => 'Cancha '.$id]);
            }
        });
    }
};
