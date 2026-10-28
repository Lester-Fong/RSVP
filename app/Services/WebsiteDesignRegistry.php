<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class WebsiteDesignRegistry
{
    /** @return list<WebsiteTemplateManifest> */
    public function templates(): array
    {
        return [
            new WebsiteTemplateManifest(
                'modern-classic', 1, [1], ['ceremony', 'reception', 'closing'],
                'wedding/ModernClassicInvitation', 'public.wedding',
                ['palettes' => ['modern-classic'], 'font_pairs' => ['modern-classic'], 'custom_css' => false], true,
            ),
            new WebsiteTemplateManifest(
                'floral-trial', 1, [2], ['welcome', 'event_details', 'closing', 'rsvp_information'],
                'reserved:website/floral-trial/1', 'reserved:website/floral-trial/1',
                ['palettes' => ['ivory-ink', 'blush-terracotta'], 'font_pairs' => ['editorial-serif', 'script-serif'], 'custom_css' => false], false,
            ),
            new WebsiteTemplateManifest(
                'enchanted-editorial', 1, [2], CmsV2Contract::SECTION_TYPES,
                'wedding/enchanted-editorial/EnchantedEditorialInvitation', 'public.enchanted',
                ['palettes' => ['ivory-ink', 'blush-terracotta'], 'font_pairs' => ['editorial-serif', 'script-serif'], 'custom_css' => false], false,
            ),
        ];
    }

    /** @return list<IntroductionStyleManifest> */
    public function introductions(): array
    {
        $variants = [
            'royal-wax-seal' => ['seal_preset' => ['round-monogram'], 'liner_preset' => ['plain']],
            'classic-ivory-letter' => ['paper_preset' => ['ivory'], 'edge_preset' => ['straight']],
            'botanical-bloom' => ['artwork_preset' => ['botanical-default']],
            'modern-folded-card' => ['fold_direction' => ['horizontal', 'vertical'], 'edge_preset' => ['straight']],
            'romantic-photo-reveal' => ['focal_point' => ['center', 'top', 'bottom'], 'scrim_preset' => ['readable']],
        ];

        $styles = [];
        foreach ($variants as $key => $options) {
            $styles[] = new IntroductionStyleManifest(
                $key, 1, [2], ['opening_text', 'open_label', 'font_preset', 'palette_preset', 'variant'],
                $options, 'reserved:intro/'.$key.'/1', 'reserved:intro/'.$key.'/1', false,
            );
        }

        $styles[] = new IntroductionStyleManifest(
            'photobooth', 1, [2], ['opening_text', 'open_label', 'font_preset', 'palette_preset', 'variant'],
            ['print_preset' => ['three-frame']],
            'wedding/enchanted-editorial/EnchantedPhotobooth', 'public.enchanted', false,
        );

        return $styles;
    }

    public function template(string $key, int $version, int $schemaVersion): WebsiteTemplateManifest
    {
        foreach ($this->templates() as $template) {
            if ($template->key === $key && $template->version === $version && in_array($schemaVersion, $template->schemaVersions, true)) {
                return $template;
            }
        }

        throw ValidationException::withMessages(['template' => 'This website template version does not support the requested CMS schema.']);
    }

    public function introduction(string $key, int $version, int $schemaVersion, WebsiteTemplateManifest $template): IntroductionStyleManifest
    {
        foreach ($this->introductions() as $style) {
            if ($style->key === $key && $style->version === $version && $style->supports($template, $schemaVersion)) {
                return $style;
            }
        }

        throw ValidationException::withMessages(['intro' => 'This introduction version does not support the requested website schema.']);
    }

    /** @return list<WebsiteTemplateManifest> */
    public function availableTemplates(): array
    {
        return array_values(array_filter($this->templates(), fn (WebsiteTemplateManifest $template): bool => $template->available));
    }

    /** @return list<IntroductionStyleManifest> */
    public function availableIntroductions(): array
    {
        return array_values(array_filter($this->introductions(), fn (IntroductionStyleManifest $style): bool => $style->available));
    }
}
