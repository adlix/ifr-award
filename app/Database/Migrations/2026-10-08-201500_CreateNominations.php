<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNominations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'nominee_first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'nominee_last_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'nominee_title' => ['type' => 'VARCHAR', 'constraint' => 150],
            'nominee_organization' => ['type' => 'VARCHAR', 'constraint' => 180],
            'nominee_email' => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'nominee_phone' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'nominee_country' => ['type' => 'VARCHAR', 'constraint' => 100],
            'nominee_biography' => ['type' => 'TEXT'],
            'nominee_contribution' => ['type' => 'TEXT'],
            'nominee_industry_service' => ['type' => 'TEXT'],
            'nominee_innovation' => ['type' => 'TEXT'],
            'reference_name' => ['type' => 'VARCHAR', 'constraint' => 200],
            'reference_organization' => ['type' => 'VARCHAR', 'constraint' => 180],
            'reference_email' => ['type' => 'VARCHAR', 'constraint' => 254],
            'nominator_first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'nominator_last_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'nominator_title' => ['type' => 'VARCHAR', 'constraint' => 150],
            'nominator_organization' => ['type' => 'VARCHAR', 'constraint' => 180],
            'nominator_email' => ['type' => 'VARCHAR', 'constraint' => 254],
            'nominator_phone' => ['type' => 'VARCHAR', 'constraint' => 60],
            'relationship_to_nominee' => ['type' => 'VARCHAR', 'constraint' => 500],
            'supporting_links' => ['type' => 'TEXT', 'null' => true],
            'eligibility_confirmed' => ['type' => 'BOOLEAN', 'default' => false],
            'attendance_confirmed' => ['type' => 'BOOLEAN', 'default' => false],
            'privacy_consent' => ['type' => 'BOOLEAN', 'default' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('category');
        $this->forge->createTable('nominations');
    }

    public function down(): void
    {
        $this->forge->dropTable('nominations');
    }
}
