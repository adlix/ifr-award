<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAttendanceConfirmationText extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('nomination_settings', [
            'attendance_confirmation_text' => [
                'type'       => 'VARCHAR',
                'constraint' => 2000,
                'default'    => 'The applicant can attend the award session on 19 April 2027 and the award ceremony on 21 June 2027.',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('nomination_settings', 'attendance_confirmation_text');
    }
}
