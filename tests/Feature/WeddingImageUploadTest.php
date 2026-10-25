<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\WebsiteRevision;
use App\Models\WeddingMediaAsset;
use App\Services\CmsV2Contract;
use App\Services\CmsV2Document;
use App\Services\CreateWebsiteRevision;
use App\Services\UploadWeddingImage;
use App\Services\WeddingImageProcessor;
use App\Services\WeddingImageResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\TestCase;

class WeddingImageUploadTest extends TestCase
{
    // Upload must own its real transaction; the test database is disposable in-memory SQLite.
    use DatabaseMigrations;

    #[DataProvider('formats')]
    public function test_supported_images_are_processed_registered_and_resolved_privately(string $format): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $facts = $wedding->fresh()->getAttributes();
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('put')->once()->with(Mockery::type('string'), Mockery::type('string'), ['visibility' => 'private'])
            ->andReturnUsing(fn (string $path, string $bytes, array $options): bool => $disk->put($path, $bytes, $options));
        Storage::shouldReceive('disk')->with('wedding_media')->andReturn($proxy);
        $result = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('private-family-name.'.$format, 2400, 1200));
        $asset = WeddingMediaAsset::findOrFail($result['id']);
        $bytes = $disk->get($asset->path);
        $this->assertSame(['id', 'kind', 'mime_type', 'byte_size', 'width', 'height'], array_keys($result));
        $this->assertSame('image/webp', $result['mime_type']);
        $this->assertSame([1600, 800, IMAGETYPE_WEBP], array_slice(getimagesizefromstring($bytes), 0, 3));
        $this->assertSame(strlen($bytes), $result['byte_size']);
        $this->assertSame(1600, $result['width']);
        $this->assertSame(800, $result['height']);
        $this->assertTrue(Str::isUuid($asset->id));
        $this->assertSame('weddings/'.$wedding->id.'/images/'.$asset->id.'.webp', $asset->path);
        $this->assertSame('private', config('filesystems.disks.wedding_media.visibility'));
        $this->assertFalse(config('filesystems.disks.wedding_media.serve'));
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame('private', $disk->getVisibility($asset->path));
        }
        $this->assertStringNotContainsString('private-family-name', json_encode($result));
        $this->assertArrayNotHasKey('path', $asset->toArray());
        $this->assertArrayNotHasKey('disk', $asset->toArray());
        $reference = $this->reference($asset->id);
        $resolved = app(WeddingImageResolver::class)->resolve($owner, $wedding, $reference);
        $this->assertSame($asset->id, $resolved['asset_id']);
        $this->assertSame('portrait', $resolved['crop']);
        $this->assertArrayNotHasKey('url', $resolved);
        $this->assertArrayNotHasKey('path', $resolved);
        $this->assertSame($bytes, app(WeddingImageResolver::class)->preview($owner, $wedding, $asset->id));
        $this->assertSame($facts, $wedding->fresh()->getAttributes());
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertCount(1, $disk->allFiles());
        $this->get('/storage/wedding-media/'.$asset->path)->assertForbidden();
        $this->get('/wedding-media/'.$asset->path)->assertNotFound();
    }

    public static function formats(): array
    {
        return [['jpg'], ['jpeg'], ['png'], ['webp']];
    }

    #[DataProvider('invalidImages')]
    public function test_invalid_images_never_create_files_or_asset_records(string $case): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $png = UploadedFile::fake()->image('valid.png');
        $file = match ($case) {
            'text' => UploadedFile::fake()->createWithContent('fake.png', 'not image bytes'),
            'svg' => UploadedFile::fake()->createWithContent('picture.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'gif' => UploadedFile::fake()->image('picture.gif'),
            'wrong extension' => UploadedFile::fake()->createWithContent('fake.jpg', file_get_contents($png->getPathname())),
            'executable extension' => UploadedFile::fake()->createWithContent('fake.png.php', file_get_contents($png->getPathname())),
            'corrupt' => UploadedFile::fake()->createWithContent('broken.png', substr(file_get_contents($png->getPathname()), 0, 35)),
            'oversized' => UploadedFile::fake()->createWithContent('big.png', file_get_contents($png->getPathname()).str_repeat('x', 5242881)),
            'dimension' => UploadedFile::fake()->image('wide.png', 6001, 1),
            'pixels' => UploadedFile::fake()->image('pixels.jpg', 4000, 3001),
            'upload error' => new UploadedFile('', 'interrupted.jpg', 'image/jpeg', UPLOAD_ERR_PARTIAL, true),
        };
        try {
            app(UploadWeddingImage::class)->upload($owner, $wedding, $file);
            $this->fail('An unsafe image was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $this->assertDatabaseCount('wedding_media_assets', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public static function invalidImages(): array
    {
        return array_map(fn (string $case): array => [$case], ['text', 'svg', 'gif', 'wrong extension', 'executable extension', 'corrupt', 'oversized', 'dimension', 'pixels', 'upload error']);
    }

    #[DataProvider('orientations')]
    public function test_orientation_and_metadata_are_normalized_by_the_shared_processor(int $orientation, int $width, int $height, int $red, int $green, int $blue): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $fixture = require base_path('tests/Fixtures/wedding-photo.php');
        $result = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->createWithContent('phone.jpg', $fixture($orientation)));
        $asset = WeddingMediaAsset::findOrFail($result['id']);
        $bytes = $disk->get($asset->path);
        $this->assertSame([$width, $height], [$result['width'], $result['height']]);
        $this->assertStringNotContainsString('Exif', $bytes);
        $this->assertStringNotContainsString('GPS_LOCATION_PRIVATE', $bytes);
        $this->assertStringNotContainsString('<script>', $bytes);
        $image = imagecreatefromstring($bytes);
        $pixel = imagecolorsforindex($image, imagecolorat($image, 3, 3));
        $this->assertEqualsWithDelta($red, $pixel['red'], 35);
        $this->assertEqualsWithDelta($green, $pixel['green'], 35);
        $this->assertEqualsWithDelta($blue, $pixel['blue'], 35);
    }

    public static function orientations(): array
    {
        return [[1, 80, 40, 255, 0, 0], [2, 80, 40, 0, 255, 0], [3, 80, 40, 255, 255, 0], [4, 80, 40, 0, 0, 255],
            [5, 40, 80, 255, 0, 0], [6, 40, 80, 0, 0, 255], [7, 40, 80, 255, 255, 0], [8, 40, 80, 0, 255, 0]];
    }

    #[DataProvider('failureScenarios')]
    public function test_storage_and_registration_failures_compensate_without_deleting_committed_or_preexisting_files(string $case): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        if ($case === 'registration') {
            WeddingMediaAsset::created(function (): void {
                throw new RuntimeException('Synthetic database registration failure.');
            });
        } elseif (in_array($case, ['commit failure', 'lost acknowledgement'], true)) {
            $first = true;
            Event::listen($case === 'commit failure' ? TransactionCommitting::class : TransactionCommitted::class, function () use (&$first): void {
                if ($first) {
                    $first = false;
                    throw new RuntimeException('Synthetic commit failure.');
                }
            });
        } else {
            $proxy = Mockery::mock($disk);
            $proxy->shouldReceive('put')->once()->andReturnUsing(function (string $path, string $bytes, array $options) use ($disk, $case): bool {
                if ($case !== 'write false') {
                    $disk->put($path, substr($bytes, 0, 10), $options);
                }
                if ($case === 'write throws') {
                    throw new RuntimeException('Synthetic private write failure.');
                }

                return false;
            });
            if ($case === 'cleanup failure') {
                $proxy->shouldReceive('delete')->andReturnFalse();
            }
            Storage::shouldReceive('disk')->with('wedding_media')->andReturn($proxy);
        }
        try {
            app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('synthetic.png'));
            $this->fail('A failed upload was reported as successful.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
            $this->assertStringNotContainsString($disk->path(''), json_encode($exception->errors()));
        }
        $this->assertDatabaseCount('wedding_media_assets', $case === 'lost acknowledgement' ? 1 : 0);
        $this->assertCount(in_array($case, ['lost acknowledgement', 'cleanup failure'], true) ? 1 : 0, $disk->allFiles());
        if ($case === 'lost acknowledgement') {
            $asset = WeddingMediaAsset::sole();
            $this->assertSame($disk->get($asset->path), app(WeddingImageResolver::class)->preview($owner, $wedding, $asset->id));
        }
    }

    public static function failureScenarios(): array
    {
        return array_map(fn (string $case): array => [$case], ['write false', 'partial write', 'write throws', 'registration', 'commit failure', 'lost acknowledgement', 'cleanup failure']);
    }

    public function test_processing_failure_writes_nothing(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $this->mock(WeddingImageProcessor::class)->shouldReceive('normalize')->once()->andThrow(ValidationException::withMessages(['image' => 'Synthetic processing failure.']));
        try {
            app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('photo.png'));
            $this->fail('Processing failure was ignored.');
        } catch (ValidationException $exception) {
            $this->assertSame('Synthetic processing failure.', $exception->errors()['image'][0]);
        }
        $this->assertDatabaseCount('wedding_media_assets', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_ambient_transactions_are_rejected_before_files_are_written(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        DB::beginTransaction();
        try {
            app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('photo.png'));
            $this->fail('An ambient transaction was accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('own their transaction', $exception->getMessage());
        } finally {
            DB::rollBack();
        }
        $this->assertSame([], $disk->allFiles());
        $this->assertDatabaseCount('wedding_media_assets', 0);
    }

    public function test_repeated_uploads_and_uuid_collisions_never_overwrite_an_asset(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $first = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('same.png'));
        $asset = WeddingMediaAsset::findOrFail($first['id']);
        $bytes = $disk->get($asset->path);
        $second = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('same.png'));
        $this->assertNotSame($first['id'], $second['id']);
        Str::createUuidsUsing(fn () => Uuid::fromString($first['id']));
        try {
            app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('replacement.png', 40, 20));
            $this->fail('A duplicate identity overwrote the asset.');
        } catch (ValidationException) {
            $this->assertSame($bytes, $disk->get($asset->path));
        } finally {
            Str::createUuidsNormally();
        }
        $this->assertDatabaseCount('wedding_media_assets', 2);
        $this->assertCount(2, $disk->allFiles());
    }

    public function test_an_existing_orphan_file_is_not_overwritten_or_deleted_on_collision(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $id = (string) Str::uuid();
        $path = 'weddings/'.$wedding->id.'/images/'.$id.'.webp';
        $disk->put($path, 'pre-existing private orphan');
        Str::createUuidsUsing(fn () => Uuid::fromString($id));
        try {
            app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('photo.png'));
            $this->fail('A pre-existing file was overwritten.');
        } catch (ValidationException) {
            $this->assertSame('pre-existing private orphan', $disk->get($path));
        } finally {
            Str::createUuidsNormally();
        }
        $this->assertDatabaseCount('wedding_media_assets', 0);
    }

    public function test_cross_account_operations_and_spoofed_wedding_ownership_are_denied(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        [$otherOwner, $otherWedding] = $this->ownedWedding();
        $asset = app(UploadWeddingImage::class)->upload($otherOwner, $otherWedding, UploadedFile::fake()->image('private.png'));
        $otherWedding->account_id = $wedding->account_id;
        foreach (['upload', 'preview', 'resolve'] as $operation) {
            try {
                match ($operation) {
                    'upload' => app(UploadWeddingImage::class)->upload($owner, $otherWedding, UploadedFile::fake()->image('photo.png')),
                    'preview' => app(WeddingImageResolver::class)->preview($owner, $otherWedding, $asset['id']),
                    'resolve' => app(WeddingImageResolver::class)->resolve($owner, $otherWedding, $this->reference($asset['id'])),
                };
                $this->fail('Foreign ownership was accepted.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('wedding_media_assets', 1);
            }
        }
        $this->assertCount(1, $disk->allFiles());
    }

    public function test_ownership_is_rechecked_after_processing_inside_the_wedding_transaction(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $processor = new WeddingImageProcessor;
        $this->mock(WeddingImageProcessor::class)->shouldReceive('normalize')->once()->andReturnUsing(function (UploadedFile $file) use ($owner, $wedding, $processor): string {
            $bytes = $processor->normalize($file);
            $wedding->account->users()->detach($owner);

            return $bytes;
        });
        try {
            app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('photo.png'));
            $this->fail('Revoked owner permission was accepted.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('wedding_media_assets', 0);
        }
        $this->assertSame([], $disk->allFiles());
    }

    public function test_document_assignment_reuses_assets_but_rejects_foreign_hidden_references(): void
    {
        Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $other = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Couple', 'public_slug' => fake()->uuid()]);
        $asset = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('own.png'));
        $foreign = app(UploadWeddingImage::class)->upload($owner, $other, UploadedFile::fake()->image('foreign.png'));
        $input = json_decode(file_get_contents(base_path('tests/Fixtures/cms-reusable-content.json')), true)['base'];
        foreach ($input['content']['sections'][0]['data']['milestones'] as &$milestone) {
            if (isset($milestone['photo'])) {
                $milestone['photo'] = $this->reference($asset['id']);
            }
        } unset($milestone);
        $input['content']['sections'][1]['data']['items'] = [$input['content']['sections'][1]['data']['items'][0]];
        $input['content']['sections'][1]['data']['items'][0]['image'] = $this->reference($asset['id']);
        $document = CmsV2Document::fromArray($input, app(CmsV2Contract::class));
        $this->assertSame([$asset['id']], array_keys(app(WeddingImageResolver::class)->validateDocument($owner, $wedding, $document)));
        $this->assertSame($input, $document->toArray());
        $input['template_key'] = 'floral-trial';
        $input['content']['sections'][1]['enabled'] = false;
        $input['content']['sections'][1]['data']['items'][0]['image'] = $this->reference($foreign['id']);
        $document = CmsV2Document::fromArray($input, app(CmsV2Contract::class));
        $this->assertSame([], $document->publicContent()['sections']);
        $this->expectException(ValidationException::class);
        app(WeddingImageResolver::class)->validateDocument($owner, $wedding, $document);
    }

    public function test_missing_corrupt_or_foreign_assets_and_manipulated_reference_settings_fail_safely(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $asset = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('photo.png'));
        foreach ([$this->reference((string) Str::uuid()), $this->reference('private/path.webp'), $this->reference($asset['id']) + ['disk' => 'public']] as $reference) {
            try {
                app(WeddingImageResolver::class)->resolve($owner, $wedding, $reference);
                $this->fail('Invalid reference resolved.');
            } catch (ValidationException $exception) {
                $this->assertStringNotContainsString($disk->path(''), json_encode($exception->errors()));
            }
        }
        $record = WeddingMediaAsset::findOrFail($asset['id']);
        foreach (['corrupt bytes', null] as $value) {
            if ($value === null) {
                $disk->delete($record->path);
            } else {
                $disk->put($record->path, $value);
            }
            try {
                app(WeddingImageResolver::class)->preview($owner, $wedding, $asset['id']);
                $this->fail('Missing/corrupt image resolved.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('image', $exception->errors());
            }
        }
        $this->assertDatabaseCount('wedding_media_assets', 1);
    }

    public function test_immutable_metadata_and_retained_revision_references_protect_uploaded_images(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $uploaded = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('photo.png'));
        $asset = WeddingMediaAsset::findOrFail($uploaded['id']);
        $revision = app(CreateWebsiteRevision::class)->create($wedding, WebsiteRevision::factory()->for($wedding)->make(), [$asset->id]);
        try {
            $asset->forceFill(['width' => 7])->save();
            $this->fail('Asset metadata changed.');
        } catch (LogicException) {
            $this->assertSame($uploaded['width'], $asset->fresh()->width);
        }
        try {
            $asset->delete();
            $this->fail('A retained asset was deleted.');
        } catch (LogicException) {
            $disk->assertExists($asset->path);
        }
        $this->assertTrue($revision->mediaAssets()->sole()->is($asset));
        $this->assertSame($disk->get($asset->path), app(WeddingImageResolver::class)->preview($owner, $wedding, $asset->id));
    }

    #[DataProvider('embeddedExifCases')]
    public function test_png_and_webp_exif_orientation_uses_the_same_metadata_free_pipeline(string $format, int $orientation, bool $bigEndian): void
    {
        Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $fixture = require base_path('tests/Fixtures/wedding-photo.php');
        $image = imagecreatefromstring($fixture(1));
        ob_start();
        $format === 'png' ? imagepng($image) : imagewebp($image);
        $source = ob_get_clean();
        $short = $bigEndian ? 'n' : 'v';
        $long = $bigEndian ? 'N' : 'V';
        $payload = ($bigEndian ? 'MM' : 'II').pack($short, 42).pack($long, 8).pack($short, 3);
        foreach ([[256, 80], [257, 40], [274, $orientation]] as [$tag, $value]) {
            $payload .= pack($short, $tag).pack($short, 3).pack($long, 1).pack($short, $value)."\0\0";
        }
        $payload .= pack($long, 0).'GPS_LOCATION_PRIVATE';
        if ($format === 'png') {
            $chunk = 'eXIf'.$payload;
            $source = substr($source, 0, 33).pack('N', strlen($payload)).$chunk.pack('H*', hash('crc32b', $chunk)).substr($source, 33);
        } else {
            $extended = chr(8)."\0\0\0".substr(pack('V', 79), 0, 3).substr(pack('V', 39), 0, 3);
            $chunk = 'EXIF'.pack('V', strlen($payload)).$payload.(strlen($payload) % 2 ? "\0" : '');
            $body = 'WEBP'.'VP8X'.pack('V', 10).$extended.substr($source, 12).$chunk;
            $source = 'RIFF'.pack('V', strlen($body)).$body;
        }
        $result = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->createWithContent('phone.'.$format, $source));
        $bytes = app(WeddingImageResolver::class)->preview($owner, $wedding, $result['id']);
        $this->assertSame($orientation === 2 ? [80, 40] : [40, 80], [$result['width'], $result['height']]);
        $this->assertStringNotContainsString('GPS_LOCATION_PRIVATE', $bytes);
        $this->assertStringNotContainsString('Exif', $bytes);
        $pixel = imagecolorsforindex($decoded = imagecreatefromstring($bytes), imagecolorat($decoded, 3, 3));
        $this->assertEqualsWithDelta($orientation === 6 ? 255 : 0, $pixel['blue'], 35);
        $this->assertEqualsWithDelta($orientation === 6 ? 0 : 255, $pixel['green'], 35);
    }

    public static function embeddedExifCases(): array
    {
        return [['png', 2, false], ['png', 6, false], ['png', 8, false], ['png', 6, true],
            ['webp', 2, false], ['webp', 6, false], ['webp', 8, false], ['webp', 6, true]];
    }

    public function test_client_mime_headers_are_not_authority_for_valid_image_bytes(): void
    {
        Storage::fake('wedding_media');
        [$owner, $wedding] = $this->ownedWedding();
        $source = UploadedFile::fake()->image('source.png');
        $file = new UploadedFile($source->getPathname(), 'renamed.png', 'text/plain', null, true);
        $result = app(UploadWeddingImage::class)->upload($owner, $wedding, $file);
        $this->assertSame('image/webp', $result['mime_type']);
    }

    private function reference(string $id): array
    {
        return ['asset_id' => $id, 'alt' => 'Synthetic wedding photo', 'focal' => 'top', 'crop' => 'portrait'];
    }

    private function ownedWedding(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Synthetic image workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Synthetic', 'partner_two_name' => 'Couple', 'public_slug' => fake()->uuid()]);

        return [$owner, $wedding];
    }
}
