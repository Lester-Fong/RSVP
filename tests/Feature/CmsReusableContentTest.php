<?php

namespace Tests\Feature;

use App\Services\CmsV2Contract;
use App\Services\CmsV2Document;
use App\Services\WebsiteDesignRegistry;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CmsReusableContentTest extends TestCase
{
    #[DataProvider('acceptedDocuments')]
    public function test_known_content_round_trips_without_losing_order_or_optional_data(array $input): void
    {
        $document = CmsV2Document::fromArray($input, app(CmsV2Contract::class));

        $this->assertSame($input, $document->toArray());
    }

    #[DataProvider('rejectedDocuments')]
    public function test_invalid_content_is_rejected_instead_of_dropped_or_coerced(array $input, string $field): void
    {
        try {
            app(CmsV2Contract::class)->validate($input);
            $this->fail('Invalid reusable content was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            $this->assertNotEmpty($exception->errors()[$field][0]);
        }
    }

    public function test_projection_excludes_hidden_entries_without_mutating_private_content(): void
    {
        $fixtures = self::fixtures();
        $document = CmsV2Document::fromArray($fixtures['base'], app(CmsV2Contract::class));

        $this->assertSame($fixtures['public_content'], $document->publicContent());
        $this->assertSame($fixtures['base'], $document->toArray());
        $this->assertStringNotContainsString('The proposal', json_encode($document->publicContent(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Close friends', json_encode($document->publicContent(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($fixtures['base']['content']['sections'][1]['data']['items'][1]['image']['asset_id'], json_encode($document->publicContent(), JSON_THROW_ON_ERROR));
        $public = $document->publicContent();
        $public['sections'][0]['data']['paragraphs'][0] = 'Changed outside the value';
        $this->assertSame($fixtures['public_content'], $document->publicContent());
    }

    public function test_template_switch_preserves_content_and_switching_back_restores_its_projection(): void
    {
        $input = self::fixtures()['base'];
        $document = CmsV2Document::fromArray($input, app(CmsV2Contract::class));
        $floral = $document->withTemplate('floral-trial', 1);

        $this->assertSame($input['content'], $floral->toArray()['content']);
        $this->assertSame([], $floral->publicContent()['sections']);
        $this->assertSame($input, $floral->withTemplate('enchanted-editorial', 1)->toArray());
        $this->assertSame($document->publicContent(), $floral->withTemplate('enchanted-editorial', 1)->publicContent());
        $this->assertSame($input, $document->toArray());
    }

    public function test_supported_sections_and_nested_entries_keep_their_saved_order(): void
    {
        $input = self::fixtures()['base'];
        $input['content']['sections'] = array_reverse($input['content']['sections']);
        $input['content']['sections'][2]['data']['milestones'][1]['enabled'] = true;
        $input['content']['sections'][2]['data']['milestones'] = array_reverse($input['content']['sections'][2]['data']['milestones']);
        $document = CmsV2Document::fromArray($input, app(CmsV2Contract::class));

        $this->assertSame(['entourage', 'gallery', 'story'], array_column($document->publicContent()['sections'], 'type'));
        $this->assertSame(['The proposal', 'First meeting'], array_column($document->publicContent()['sections'][2]['data']['milestones'], 'title'));
        $this->assertSame($input, $document->toArray());
    }

    public function test_disabled_sections_are_omitted_even_when_the_template_supports_them(): void
    {
        $input = self::fixtures()['base'];
        $input['content']['sections'][0]['enabled'] = false;
        $document = CmsV2Document::fromArray($input, app(CmsV2Contract::class));

        $this->assertSame(['gallery', 'entourage'], array_column($document->publicContent()['sections'], 'type'));
        $this->assertSame($input, $document->toArray());
    }

    public function test_disabled_intro_selects_no_style_and_adds_no_available_design(): void
    {
        $document = CmsV2Document::fromArray(self::fixtures()['base'], app(CmsV2Contract::class));
        $registry = app(WebsiteDesignRegistry::class);

        $this->assertSame(['enabled' => false], $document->publicContent()['experience']);
        $this->assertSame(['royal-wax-seal', 'classic-ivory-letter', 'botanical-bloom', 'modern-folded-card', 'romantic-photo-reveal', 'photobooth'], array_column($registry->introductions(), 'key'));
        $this->assertSame([], $registry->availableIntroductions());
        $this->assertSame(['modern-classic'], array_column($registry->availableTemplates(), 'key'));
        $this->assertFalse($registry->template('enchanted-editorial', 1, 2)->available);
    }

    public function test_an_unknown_template_version_cannot_be_selected(): void
    {
        $document = CmsV2Document::fromArray(self::fixtures()['base'], app(CmsV2Contract::class));

        $this->expectException(ValidationException::class);
        $document->withTemplate('enchanted-editorial', 2);
    }

    public function test_unsupported_content_is_still_validated_before_projection(): void
    {
        $input = self::fixtures()['base'];
        $input['template_key'] = 'floral-trial';
        $input['content']['sections'][0]['data']['private_guests'] = ['Synthetic hidden contact'];

        $this->expectException(ValidationException::class);
        app(CmsV2Contract::class)->project($input);
    }

    public function test_duplicate_gallery_assets_are_rejected_even_when_hidden_without_banning_reuse_in_story(): void
    {
        $input = self::fixtures()['base'];
        $asset = $input['content']['sections'][1]['data']['items'][0]['image'];
        $input['content']['sections'][0]['data']['milestones'][0]['photo'] = $asset;
        $this->assertSame($input, app(CmsV2Contract::class)->validate($input));
        $input['content']['sections'][1]['enabled'] = false;
        $input['content']['sections'][1]['data']['items'][1]['image'] = $asset;

        try {
            app(CmsV2Contract::class)->project($input);
            $this->fail('Duplicate gallery references must fail before projection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content.sections.1.data.items.0.image.asset_id', $exception->errors());
        }
    }

    public function test_reusable_content_cannot_bypass_the_document_byte_budget(): void
    {
        $input = self::fixtures()['base'];
        $input['content']['sections'][0]['data']['paragraphs'] = [str_repeat('x', CmsV2Contract::MAX_BYTES)];

        try {
            app(CmsV2Contract::class)->validate($input);
            $this->fail('An oversized document was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function acceptedDocuments(): array
    {
        $fixtures = self::fixtures();
        $cases = [];
        foreach ($fixtures['accepted'] as $case) {
            $cases[$case['name']] = [self::changedDocument($fixtures['base'], $case['changes'])];
        }

        return $cases;
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function rejectedDocuments(): array
    {
        $fixtures = self::fixtures();
        $cases = [];
        foreach ($fixtures['rejected'] as $case) {
            $cases[$case['name']] = [self::changedDocument($fixtures['base'], $case['changes']), $case['error']];
        }

        return $cases;
    }

    /** @return array<string, mixed> */
    private static function fixtures(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/cms-reusable-content.json'), true, 32, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function changedDocument(array $document, array $changes): array
    {
        foreach ($changes as $path => $value) {
            Arr::set($document, $path, $value);
        }

        return $document;
    }
}
