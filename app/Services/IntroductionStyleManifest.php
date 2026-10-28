<?php

namespace App\Services;

final readonly class IntroductionStyleManifest
{
    /**
     * @param  list<int>  $schemaVersions
     * @param  list<string>  $sharedSettings
     * @param  array<string, list<string>>  $variantOptions
     */
    public function __construct(
        public string $key,
        public int $version,
        public array $schemaVersions,
        public array $sharedSettings,
        public array $variantOptions,
        public string $renderer,
        public string $fallback,
        public bool $available,
    ) {}

    public function supports(WebsiteTemplateManifest $template, int $schemaVersion): bool
    {
        return in_array($schemaVersion, $this->schemaVersions, true)
            && in_array($schemaVersion, $template->schemaVersions, true);
    }
}
