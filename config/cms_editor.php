<?php

return [
    // The editor is available only in local/testing or explicitly authorized staging.
    'preview_enabled' => env('CMS_EDITOR_PREVIEW_ENABLED', false) === true,
    // Production remains denied even if both feature flags and staging authorization are set.
    'publishing_enabled' => env('CMS_V2_PUBLISHING_ENABLED', false) === true,
    'staging_authorized' => env('CMS_V2_STAGING_AUTHORIZED', false) === true,
];
