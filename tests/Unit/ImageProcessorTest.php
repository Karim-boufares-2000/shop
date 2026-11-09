<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ImageProcessor;
use App\Services\UploadException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestImages;

final class ImageProcessorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        // Assigné AVANT le skip : PHPUnit exécute tearDown() même quand setUp() saute le test.
        $this->dir = sys_get_temp_dir() . '/shop_img_' . bin2hex(random_bytes(4));
        if (!TestImages::available()) {
            $this->markTestSkipped('Extensions GD et fileinfo requises.');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    private function processor(bool $requireHttpUpload = false): ImageProcessor
    {
        return new ImageProcessor($this->dir, $requireHttpUpload);
    }

    private function assertRejected(array $file, string $expectedKey, ?ImageProcessor $processor = null): void
    {
        try {
            ($processor ?? $this->processor())->store($file);
            $this->fail('UploadException attendue : ' . $expectedKey);
        } catch (UploadException $e) {
            $this->assertSame($expectedKey, $e->key());
        }
        $this->assertSame([], glob($this->dir . '/*') ?: [], 'aucun fichier ne doit être conservé après un refus');
    }

    public function testValidPngIsReencodedAsJpegWithRandomName(): void
    {
        $id = $this->processor()->store(TestImages::upload(TestImages::png(300, 200), 'ma photo.png'));

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
        $this->assertFileExists($this->dir . '/' . $id . '.jpg');
        $this->assertFileExists($this->dir . '/' . $id . '_t.jpg');
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($this->dir . '/' . $id . '.jpg')[2]);
    }

    public function testLargeImagesAreResized(): void
    {
        $id = $this->processor()->store(TestImages::upload(TestImages::jpeg(2400, 1800), 'big.jpg'));
        [$w, $h] = getimagesize($this->dir . '/' . $id . '.jpg');
        [$tw, $th] = getimagesize($this->dir . '/' . $id . '_t.jpg');

        $this->assertLessThanOrEqual(ImageProcessor::FULL_SIDE, max($w, $h));
        $this->assertLessThanOrEqual(ImageProcessor::THUMB_SIDE, max($tw, $th));
        $this->assertEqualsWithDelta(2400 / 1800, $w / $h, 0.02); // proportions conservées
    }

    public function testOriginalNameAndExtensionAreIgnored(): void
    {
        $id = $this->processor()->store(TestImages::upload(TestImages::png(200, 200), 'evil.php'));

        $this->assertFileExists($this->dir . '/' . $id . '.jpg');
        $this->assertSame([], glob($this->dir . '/*.php') ?: []);
    }

    public function testTextFileRenamedAsImageIsRejected(): void
    {
        $this->assertRejected(TestImages::upload(TestImages::raw("<?php echo 'pwned'; ?>"), 'photo.jpg'), 'upload.invalid_type');
    }

    public function testGifIsRejected(): void
    {
        $this->assertRejected(TestImages::upload(TestImages::gif(200, 200), 'anim.gif'), 'upload.invalid_type');
    }

    public function testTooSmallImageIsRejected(): void
    {
        $this->assertRejected(TestImages::upload(TestImages::png(50, 50)), 'upload.invalid_dimensions');
    }

    public function testOversizedFileIsRejectedBeforeDecoding(): void
    {
        $this->assertRejected(TestImages::upload(TestImages::raw(str_repeat('a', ImageProcessor::MAX_BYTES + 1))), 'upload.too_large');
    }

    public function testEmptyFileIsRejected(): void
    {
        $this->assertRejected(TestImages::upload(TestImages::raw('')), 'upload.invalid_type');
    }

    public function testPayloadAppendedToValidImageIsStripped(): void
    {
        $path = TestImages::png(200, 200);
        file_put_contents($path, "\n<?php system(\$_GET['c']); ?>", FILE_APPEND);

        $id = $this->processor()->store(TestImages::upload($path, 'polyglot.png'));

        $this->assertStringNotContainsString('<?php', (string) file_get_contents($this->dir . '/' . $id . '.jpg'));
        $this->assertStringNotContainsString('<?php', (string) file_get_contents($this->dir . '/' . $id . '_t.jpg'));
    }

    public function testPhpUploadErrorCodesMapToKeys(): void
    {
        $path = TestImages::png(200, 200);

        $this->assertRejected(TestImages::upload($path, 'a.png', UPLOAD_ERR_NO_FILE), 'upload.none');
        $this->assertRejected(TestImages::upload($path, 'a.png', UPLOAD_ERR_INI_SIZE), 'upload.too_large');
        $this->assertRejected(TestImages::upload($path, 'a.png', UPLOAD_ERR_FORM_SIZE), 'upload.too_large');
        $this->assertRejected(TestImages::upload($path, 'a.png', UPLOAD_ERR_PARTIAL), 'upload.failed');
    }

    public function testFileNotComingFromHttpUploadIsRejectedByDefault(): void
    {
        // is_uploaded_file() est faux pour un fichier créé localement : protège contre un chemin tmp_name forgé.
        $this->assertRejected(TestImages::upload(TestImages::png(200, 200)), 'upload.failed', $this->processor(true));
    }

    public function testDeleteRemovesBothFilesAndIgnoresUnsafeIds(): void
    {
        $processor = $this->processor();
        $id = $processor->store(TestImages::upload(TestImages::png(200, 200)));

        $outside = sys_get_temp_dir() . '/shop_outside_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($outside, 'x');
        $processor->delete('../' . basename($outside, '.jpg'));
        $processor->delete('..\\..\\x');
        $processor->delete('not-a-valid-id');
        $this->assertFileExists($outside);
        @unlink($outside);

        $processor->delete($id);
        $this->assertFileDoesNotExist($this->dir . '/' . $id . '.jpg');
        $this->assertFileDoesNotExist($this->dir . '/' . $id . '_t.jpg');
    }
}
