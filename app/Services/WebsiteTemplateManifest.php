<?php

namespace App\Services;

final readonly class WebsiteTemplateManifest
{
    /**
     * @param  list<int>  $schemaVersions
     * @param  list<string>  $sectionTypes
     * @param  array{palettes: list<string>, font_pairs: list<string>, custom_css: bool}  $themeCapabilities
     */
    public function __construct(
        public string $key,
        public int $version,
        public array $schemaVersions,
        public array $sectionTypes,
        public string $renderer,
        public string $fallback,
        public array $themeCapabilities,
        public bool $available,
    ) {}
}
