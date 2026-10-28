<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use App\Services\CmsDraft;
use App\Services\CmsEditorPreview;
use PHPUnit\Framework\Attributes\Group;

#[Group('mysql-concurrency')]
class CmsPersonalRsvpMySqlTest extends PersonalRsvpConcurrencyTest
{
    protected function createDraft(User $owner, Wedding $wedding): void
    {
        config(['cms_editor.preview_enabled' => true, 'cms_editor.publishing_enabled' => true]);
        app(CmsDraft::class)->save($owner, $wedding, CmsEditorPreview::document(), 0);
    }
}
