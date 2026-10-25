<?php

return [
    // Incomplete editor is never available in production, even if this flag is set.
    'preview_enabled' => env('CMS_EDITOR_PREVIEW_ENABLED', false) === true,
    // DEL-03b acceptance gate: production remains denied even if explicitly set.
    'publishing_enabled' => env('CMS_V2_PUBLISHING_ENABLED', false) === true,
];
