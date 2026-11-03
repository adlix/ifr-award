<?php

namespace App\Models;

use CodeIgniter\Model;

class NominationModel extends Model
{
    protected $table         = 'nominations';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'user_id',
        'category',
        'nominee_first_name',
        'nominee_last_name',
        'nominee_title',
        'nominee_role',
        'solution_name',
        'solution_description',
        'solution_reason',
        'nominee_organization',
        'nominee_email',
        'nominee_phone',
        'nominee_country',
        'nominee_biography',
        'nominee_contribution',
        'nominee_industry_service',
        'nominee_innovation',
        'reference_name',
        'reference_organization',
        'reference_email',
        'nominator_first_name',
        'nominator_last_name',
        'nominator_title',
        'nominator_organization',
        'nominator_email',
        'nominator_phone',
        'relationship_to_nominee',
        'supporting_links',
        'eligibility_confirmed',
        'attendance_confirmed',
        'privacy_consent',
    ];
}
