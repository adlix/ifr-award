<?php

use App\Controllers\Nomination;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class NominationValidationTest extends CIUnitTestCase
{
    public function testNewFormAndCharacterLimits(): void
    {
        $rules = (new ReflectionClass(Nomination::class))->getConstant('RULES');
        $values = [
            'nominee_first_name' => 'Alex',
            'nominee_last_name' => 'Example',
            'nominee_title' => 'Dr',
            'nominee_role' => 'Engineer',
            'nominee_organization' => 'Example company',
            'nominee_country' => 'Germany',
            'solution_name' => 'Example solution',
            'solution_description' => str_repeat('a', 300),
            'solution_reason' => str_repeat('b', 300),
            'eligibility_confirmed' => '1',
            'attendance_confirmed' => '1',
            'privacy_consent' => '1',
        ];

        $validation = Services::validation();
        $validation->setRules($rules);
        $this->assertTrue($validation->run($values), json_encode($validation->getErrors()));

        foreach (['solution_description', 'solution_reason'] as $field) {
            $tooLong = $values;
            $tooLong[$field] .= 'x';
            $validation->reset()->setRules($rules);
            $this->assertFalse($validation->run($tooLong));
            $this->assertArrayHasKey($field, $validation->getErrors());
        }
    }
}
