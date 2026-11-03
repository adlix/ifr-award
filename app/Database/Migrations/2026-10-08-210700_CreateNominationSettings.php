<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNominationSettings extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
            ],
            'starts_on' => [
                'type' => 'DATE',
            ],
            'ends_on' => [
                'type' => 'DATE',
            ],
            'updated_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('nomination_settings');

        $now  = date('Y-m-d H:i:s');
        $year = date('Y');

        $this->db->table('nomination_settings')->insert([
            'id'         => 1,
            'starts_on'  => $year . '-01-01',
            'ends_on'    => $year . '-12-31',
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('nomination_settings');
    }
}
