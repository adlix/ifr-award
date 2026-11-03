<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowAnonymousNominations extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('nominations', [
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->modifyColumn('nominations', [
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);
    }
}
