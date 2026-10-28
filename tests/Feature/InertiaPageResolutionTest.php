<?php

namespace Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InertiaPageResolutionTest extends TestCase
{
    public function test_application_and_assertion_resolvers_find_the_same_lowercase_vue_pages(): void
    {
        $this->assertSame([resource_path('js/pages')], config('inertia.page_paths'));
        $this->assertSame(config('inertia.page_paths'), config('inertia.testing.page_paths'));
        $this->assertTrue(config('inertia.testing.ensure_pages_exist'));

        foreach (['inertia.view-finder', 'inertia.testing.view-finder'] as $resolver) {
            foreach (['Dashboard', 'weddings/CmsEditorPreview', 'weddings/CmsSavedPreview', 'weddings/InvitationEditor', 'weddings/Attendance', 'weddings/Guests', 'weddings/Preview'] as $component) {
                $this->assertSame(realpath(resource_path('js/pages/'.$component.'.vue')), realpath(app($resolver)->find($component)));
            }
        }
    }

    #[DataProvider('pageResolvers')]
    public function test_missing_pages_are_rejected_by_both_resolvers(string $resolver): void
    {
        $this->expectException(InvalidArgumentException::class);

        app($resolver)->find('weddings/NonexistentPage');
    }

    /** @return array<string, array{string}> */
    public static function pageResolvers(): array
    {
        return [
            'application' => ['inertia.view-finder'],
            'assertions' => ['inertia.testing.view-finder'],
        ];
    }
}
