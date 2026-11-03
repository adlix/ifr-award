<?php

namespace App\Models;

use CodeIgniter\Model;

class NominationDocumentModel extends Model
{
    protected $table      = 'nomination_documents';
    protected $returnType = 'array';

    protected $allowedFields = [
        'nomination_id',
        'original_name',
        'stored_name',
        'mime_type',
        'size_bytes',
        'created_at',
    ];
}
