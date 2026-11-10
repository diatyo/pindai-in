<?php

use App\Libraries\SchoolLetterhead;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

final class SchoolLetterheadTest extends CIUnitTestCase
{
    public function testOldSettingsRetainTheExistingLetterheadDefaults(): void
    {
        $values = SchoolLetterhead::values(['school_name' => 'Identitas aplikasi']);
        self::assertSame('PEMERINTAH KOTA TANJUNGPINANG', $values['letterhead_government']);
        self::assertSame(14, $values['letterhead_government_size']);
        self::assertStringStartsWith('data:image/png;base64,', SchoolLetterhead::logoData('letterhead_city_logo', $values));
        self::assertSame('', SchoolLetterhead::values(['letterhead_accreditation' => ''])['letterhead_accreditation']);
    }

    public function testPdfSizesAreClampedAndLogoPathsCannotEscapeStorage(): void
    {
        $values = SchoolLetterhead::values(['letterhead_school_size' => '999', 'letterhead_city_height' => '-1']);
        self::assertSame(18, $values['letterhead_school_size']);
        self::assertSame(40, $values['letterhead_city_height']);
        self::assertNull(SchoolLetterhead::logoFile('../../.env'));
        self::assertNull(SchoolLetterhead::logoFile('https://example.com/logo.png'));
        self::assertNotNull(SchoolLetterhead::logoFile('logo-' . str_repeat('a', 32) . '.png'));
    }

    public function testUploadedRasterIsReencodedAsASquareTransparentPng(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'letterhead-test-');
        try {
            $image = imagecreatetruecolor(1000, 500);
            imagepng($image, $path);
            imagedestroy($image);
            $upload = new class($path, 'logo.png', 'image/png', filesize($path), UPLOAD_ERR_OK) extends UploadedFile {
                public function isValid(): bool { return true; }
            };
            $bytes = SchoolLetterhead::readUpload($upload);
            $info = getimagesizefromstring($bytes);
            self::assertSame([600, 600, IMAGETYPE_PNG], array_slice($info, 0, 3));
            $result = imagecreatefromstring($bytes);
            self::assertSame(127, (imagecolorat($result, 0, 0) >> 24) & 127);
            imagedestroy($result);
        } finally { unlink($path); }
    }

    public function testSvgCannotBeSavedAsALogo(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'letterhead-svg-');
        try {
            file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
            $upload = new class($path, 'logo.svg', 'image/svg+xml', filesize($path), UPLOAD_ERR_OK) extends UploadedFile {
                public function isValid(): bool { return true; }
            };
            $this->expectException(DomainException::class);
            SchoolLetterhead::readUpload($upload);
        } finally { unlink($path); }
    }
}
