<?php

namespace Tests\Feature;

use App\Services\CmsEditorPreview;
use App\Services\CmsV2Contract;
use App\Services\CmsV2Document;
use App\Services\WebsiteDesignRegistry;
use App\Services\WebsiteTemplateManifest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CmsV2ContractTest extends TestCase
{
    #[DataProvider('acceptedDocuments')]
    public function test_validated_contracts_round_trip_without_changing_template_or_intro_settings(array $input): void
    {
        $document = CmsV2Document::fromArray($input, app(CmsV2Contract::class));

        $this->assertSame($input, $document->toArray());
        $this->assertSame('floral-trial', $document->templateKey);
    }

    #[DataProvider('rejectedDocuments')]
    public function test_unsupported_or_unsafe_contracts_fail_with_validation_errors(array $input, string $field): void
    {
        try {
            CmsV2Document::fromArray($input, app(CmsV2Contract::class));
            $this->fail('An invalid CMS contract was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_public_content_excludes_disabled_sections_and_draft_metadata_without_mutating_the_document(): void
    {
        $fixtures = self::fixtures();
        $document = CmsV2Document::fromArray($fixtures['base'], app(CmsV2Contract::class));

        $public = $document->publicContent();
        $public['sections'][0]['data']['message'] = 'Changed outside the document';

        $this->assertSame($fixtures['public_content'], $document->publicContent());
        $this->assertSame($fixtures['base'], $document->toArray());
        $this->assertSame(['theme', 'experience', 'sections'], array_keys($document->publicContent()));
        $this->assertStringNotContainsString('Private draft-only closing', json_encode($document->publicContent(), JSON_THROW_ON_ERROR));
    }

    public function test_countdown_is_an_optional_additive_enchanted_section_and_keeps_legacy_documents_unchanged(): void
    {
        $contract = app(CmsV2Contract::class);
        $legacy = self::fixtures()['base'];
        $this->assertSame($legacy, $contract->validate($legacy));

        $document = CmsEditorPreview::document();
        $sections = $document['content']['sections'];
        $this->assertSame(['welcome', 'event_details', 'countdown', 'story', 'gallery', 'entourage', 'guest_guide', 'rsvp_information', 'closing'], array_column($sections, 'type'));
        $countdown = $sections[2];
        $this->assertTrue($countdown['enabled']);
        $this->assertSame(['heading', 'subtitle'], array_keys($countdown['data']));

        $validated = $contract->validate($document);
        $this->assertSame($document, $validated);
        $this->assertSame($document, CmsV2Document::fromArray($validated, $contract)->toArray());
        $defaultProjection = CmsV2Document::fromArray($validated, $contract)->publicContent();
        $this->assertSame(['faqs'], array_keys($defaultProjection['sections'][6]['data']));

        $hidden = $document;
        $hidden['content']['sections'][2]['enabled'] = false;
        $projection = CmsV2Document::fromArray($hidden, $contract)->publicContent();
        $this->assertSame($hidden, CmsV2Document::fromArray($hidden, $contract)->toArray());
        $this->assertNotContains('countdown', array_column($projection['sections'], 'type'));

        $unsupported = self::fixtures()['base'];
        $unsupported['content']['sections'][] = $countdown;
        $this->expectException(ValidationException::class);
        $contract->validate($unsupported);
    }

    public function test_guest_guide_defaults_are_additive_and_block_visibility_is_only_applied_to_projection(): void
    {
        $contract = app(CmsV2Contract::class);
        $document = CmsEditorPreview::document();
        $guide = $document['content']['sections'][6];

        $this->assertSame('guest_guide', $guide['type']);
        $this->assertTrue($guide['enabled']);
        $this->assertFalse($guide['data']['attire']['enabled']);
        $this->assertFalse($guide['data']['gifts']['enabled']);
        $this->assertSame([
            'Are my kids welcome?',
            'Can I bring a plus one?',
            'How do I RSVP?',
        ], array_column($guide['data']['faqs']['items'], 'question'));

        $source = $document;
        $source['content']['sections'][6]['data']['faqs']['items'][0]['answer'] = 'Private hidden answer sentinel';
        $source['content']['sections'][6]['data']['faqs']['enabled'] = false;
        $source['content']['sections'][6]['data']['attire']['enabled'] = true;
        $projection = CmsV2Document::fromArray($source, $contract)->publicContent();
        $this->assertSame($source, CmsV2Document::fromArray($source, $contract)->toArray());
        $projectedGuide = $projection['sections'][6];
        $this->assertSame(['attire'], array_keys($projectedGuide['data']));
        $this->assertStringNotContainsString('Private hidden answer sentinel', json_encode($projection, JSON_THROW_ON_ERROR));

        $source['content']['sections'][6]['enabled'] = false;
        $hiddenProjection = CmsV2Document::fromArray($source, $contract)->publicContent();
        $this->assertNotContains('guest_guide', array_column($hiddenProjection['sections'], 'type'));
    }

    public function test_guest_guide_validates_five_swatches_seven_faqs_and_rejects_overflow_markup_and_unsafe_links(): void
    {
        $contract = app(CmsV2Contract::class);
        $document = CmsEditorPreview::document();
        $guide = &$document['content']['sections'][6]['data'];
        $guide['attire']['enabled'] = true;
        $guide['attire']['dress_code'] = 'Garden formal';
        $guide['attire']['description'] = 'A little color is welcome.';
        $guide['attire']['swatches'] = array_map(fn (int $index): array => [
            'id' => (string) Str::uuid(), 'label' => 'Shade '.$index, 'color' => '#A36E59',
        ], range(1, 5));
        for ($index = 3; $index < 7; $index++) {
            $guide['faqs']['items'][] = ['id' => (string) Str::uuid(), 'question' => 'Question '.$index, 'answer' => 'Answer '.$index];
        }
        $guide['faqs']['enabled'] = true;
        $this->assertSame($document, $contract->validate($document));

        $tooManyFaqs = $document;
        $tooManyFaqs['content']['sections'][6]['data']['faqs']['items'][] = ['id' => (string) Str::uuid(), 'question' => 'Question eight', 'answer' => 'Answer'];
        try {
            $contract->validate($tooManyFaqs);
            $this->fail('Eight FAQ entries must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content.sections.6.data.faqs.items', $exception->errors());
        }

        $tooManySwatches = $document;
        $tooManySwatches['content']['sections'][6]['data']['attire']['swatches'][] = ['id' => (string) Str::uuid(), 'label' => 'Extra', 'color' => '#A36E59'];
        try {
            $contract->validate($tooManySwatches);
            $this->fail('Six swatches must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content.sections.6.data.attire.swatches', $exception->errors());
        }

        foreach ([
            ['gifts', 'url', 'javascript:alert(1)'],
            ['gifts', 'url', 'http://example.test/registry'],
            ['gifts', 'url', 'https://example.test/rsvp/'.str_repeat('a', 64)],
            ['faqs.items.0', 'answer', '<script>unsafe</script>'],
            ['faqs.items.0', 'answer', 'https://example.test/rsvp/'.str_repeat('a', 64)],
        ] as [$path, $field, $value]) {
            $unsafe = $document;
            $target = &$unsafe['content']['sections'][6]['data'];
            foreach (explode('.', $path) as $part) {
                $target = &$target[$part];
            }
            $target[$field] = $value;
            try {
                $contract->validate($unsafe);
                $this->fail('Unsafe Guest Guide content must be rejected.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_all_five_introductions_support_any_template_with_a_compatible_schema_independently_of_availability(): void
    {
        $registry = app(WebsiteDesignRegistry::class);
        $first = $registry->template('floral-trial', 1, 2);
        $future = new WebsiteTemplateManifest('test-only-future', 8, [2], $first->sectionTypes, 'test-only', 'test-only', $first->themeCapabilities, false);

        foreach (self::fixtures()['variants'] as $key => $variant) {
            $style = $registry->introduction($key, 1, 2, $first);
            $this->assertSame($key, $registry->introduction($key, 1, 2, $future)->key);
            $this->assertFalse($style->available);
            $this->assertSame(array_keys($variant), array_keys($style->variantOptions));
        }
        $this->assertCount(6, $registry->introductions());
    }

    public function test_only_implemented_modern_classic_is_available_and_its_renderers_exist(): void
    {
        $registry = app(WebsiteDesignRegistry::class);
        $template = $registry->template('modern-classic', 1, 1);

        $this->assertSame(['modern-classic'], array_map(fn (WebsiteTemplateManifest $manifest): string => $manifest->key, $registry->availableTemplates()));
        $this->assertSame([], $registry->availableIntroductions());
        $this->assertFileExists(resource_path('js/components/'.$template->renderer.'.vue'));
        $this->assertTrue(view()->exists($template->fallback));
        $this->assertSame([1], $template->schemaVersions);
        $this->assertFalse($registry->template('floral-trial', 1, 2)->available);
    }

    public function test_intros_cannot_be_resolved_for_schema_one_modern_classic(): void
    {
        $registry = app(WebsiteDesignRegistry::class);
        $template = $registry->template('modern-classic', 1, 1);

        $this->expectException(ValidationException::class);
        $registry->introduction('botanical-bloom', 1, 1, $template);
    }

    public function test_missing_fields_and_excessive_json_depth_or_size_fail_before_projection(): void
    {
        $contract = app(CmsV2Contract::class);
        foreach (['schema_version', 'template_key', 'template_version', 'content.theme', 'content.experience.intro_settings.open_label', 'content.sections.0.data.message'] as $field) {
            $input = self::fixtures()['base'];
            Arr::forget($input, $field);
            try {
                $contract->validate($input);
                $this->fail('A missing required field was accepted: '.$field);
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        foreach ([str_repeat('x', CmsV2Contract::MAX_BYTES), array_fill(0, 13, 'nested')] as $value) {
            $input = self::fixtures()['base'];
            if (is_array($value)) {
                $nested = 'text';
                foreach ($value as $unused) {
                    $nested = ['nested' => $nested];
                }
                $input['content']['nested'] = $nested;
            } else {
                $input['content']['sections'][0]['data']['message'] = $value;
            }
            try {
                $contract->validate($input);
                $this->fail('Unbounded JSON content was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('document', $exception->errors());
            }
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
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/cms-v2-contracts.json'), true, 32, JSON_THROW_ON_ERROR);
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
