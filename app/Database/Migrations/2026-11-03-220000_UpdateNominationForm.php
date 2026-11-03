<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateNominationForm extends Migration
{
    public function up(): void
    {
        // Keep the previous fields for nominations submitted with the old form.
        $this->forge->modifyColumn('nominations', [
            'category'               => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'nominee_biography'      => ['type' => 'TEXT', 'null' => true],
            'nominee_contribution'   => ['type' => 'TEXT', 'null' => true],
            'nominee_industry_service' => ['type' => 'TEXT', 'null' => true],
            'nominee_innovation'     => ['type' => 'TEXT', 'null' => true],
            'reference_name'         => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'reference_organization' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'reference_email'        => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'nominator_first_name'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'nominator_last_name'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'nominator_title'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'nominator_organization' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'nominator_email'        => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'nominator_phone'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'relationship_to_nominee' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);
        $this->forge->addColumn('nominations', [
            'nominee_role'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'solution_name'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'solution_description' => ['type' => 'TEXT', 'null' => true],
            'solution_reason'      => ['type' => 'TEXT', 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('nominations', ['nominee_role', 'solution_name', 'solution_description', 'solution_reason']);
    }
}
