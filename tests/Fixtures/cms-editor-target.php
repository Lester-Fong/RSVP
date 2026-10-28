<?php

/** Fail before database work unless the actual resolved configuration matches this disposable root. */
$previewRoot = realpath((string) getenv('CMS_PREVIEW_DISPOSABLE_ROOT'));
$previewDatabase = realpath((string) config('database.connections.sqlite.database'));
if (! $previewRoot || ! $previewDatabase
    || ! str_starts_with(basename($previewRoot), 'rsvp-cms-editor-')
    || dirname($previewDatabase) !== $previewRoot
    || realpath(sys_get_temp_dir()) !== dirname($previewRoot)
    || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.url')
    || config('rsvp.mode') !== 'personal'
    || ! app()->environment(['local', 'staging'])
    || config('cms_editor.preview_enabled') !== true
    || realpath(storage_path()) !== realpath($previewRoot.'/storage')) {
    throw new RuntimeException('The editor harness requires its own disposable temporary SQLite database and storage. Checks: '.json_encode([
        'root' => (bool) $previewRoot, 'database' => (bool) $previewDatabase,
        'parent' => dirname($previewDatabase ?: '') === $previewRoot,
        'temp' => realpath(sys_get_temp_dir()) === dirname($previewRoot ?: ''),
        'sqlite' => config('database.default') === 'sqlite', 'urlAbsent' => ! config('database.connections.sqlite.url'),
        'isolatedEnvironment' => app()->environment(['local', 'staging']), 'enabled' => config('cms_editor.preview_enabled') === true,
        'storage' => realpath(storage_path()) === realpath(($previewRoot ?: '').'/storage'),
    ]));
}
