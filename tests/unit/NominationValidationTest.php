<?php

use App\Controllers\Nomination;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Services;

final class NominationValidationTest extends CIUnitTestCase
{
    public function testNewFormAndCharacterLimits(): void
    {
        $rules = (new ReflectionClass(Nomination::class))->getConstant('RULES');
        $values = [
            'nominee_first_name' => 'Alex',
            'nominee_last_name' => 'Example',
            'nominee_email' => 'alex@example.com',
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

        foreach ([null, '', 'not-an-email'] as $email) {
            $invalidEmail = $values;
            if ($email === null) {
                unset($invalidEmail['nominee_email']);
            } else {
                $invalidEmail['nominee_email'] = $email;
            }
            $validation->reset()->setRules($rules);
            $this->assertFalse($validation->run($invalidEmail));
            $this->assertArrayHasKey('nominee_email', $validation->getErrors());
        }

        foreach (['solution_description', 'solution_reason'] as $field) {
            $tooLong = $values;
            $tooLong[$field] .= 'x';
            $validation->reset()->setRules($rules);
            $this->assertFalse($validation->run($tooLong));
            $this->assertArrayHasKey($field, $validation->getErrors());
        }
    }
    public function testUploadSizeBoundary(): void
    {
        $controller = new Nomination();
        $validate = new ReflectionMethod(Nomination::class, 'validateDocuments');
        $document = $this->createMock(UploadedFile::class);
        $document->method('getClientName')->willReturn('solution.mp4');
        $document->method('getClientExtension')->willReturn('mp4');
        $document->method('getMimeType')->willReturn('video/mp4');
        $document->method('getError')->willReturn(UPLOAD_ERR_OK);
        $document->method('isValid')->willReturn(true);
        $document->method('getSize')->willReturnOnConsecutiveCalls(
            200 * 1024 * 1024,
            200 * 1024 * 1024 + 1,
        );

        $this->assertNull($validate->invoke($controller, [$document]));
        $this->assertSame('Each document may be no larger than 200 MB.', $validate->invoke($controller, [$document]));
    }

    public function testTitleIsNotRequiredForStorage(): void
    {
        $data = (new ReflectionMethod(Nomination::class, 'nominationData'))->invoke(new Nomination(), [], null);

        $this->assertSame('', $data['nominee_title']);
    }
}
