<?php

namespace App\Models;

use CodeIgniter\Model;

class NominationSettingsModel extends Model
{
    protected $table         = 'nomination_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'starts_on',
        'ends_on',
        'updated_by',
    ];

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return $this->find(1) ?? [
            'id'         => 1,
            'starts_on'  => null,
            'ends_on'    => null,
            'updated_by' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public function isOpen(?array $settings = null): bool
    {
        $settings ??= $this->current();
        $today = date('Y-m-d');

        return $settings['starts_on'] !== null
            && $settings['ends_on'] !== null
            && $today >= $settings['starts_on']
            && $today <= $settings['ends_on'];
    }
}
