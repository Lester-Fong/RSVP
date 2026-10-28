<?php

use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Services\CmsDraft;
use App\Services\CmsEditorPreview;
use App\Services\PersonalInvitationLink;
use App\Services\RsvpDelegationManager;
use App\Services\RsvpMode;
use App\Services\WeddingPublication;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->useStoragePath((string) getenv('LARAVEL_STORAGE_PATH'));
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/cms-editor-target.php';
if (in_array('--verify', $argv, true)) {
    echo json_encode(['driver' => 'sqlite', 'isolated' => true]);
    exit;
}
$owner = User::factory()->create(['name' => 'Editor Preview Owner', 'email' => 'editor-preview@example.test',
    'password' => Hash::make(getenv('CMS_PREVIEW_PASSWORD'))]);
$account = Account::create(['name' => 'Synthetic editor workspace']);
$account->users()->attach($owner, ['role' => 'owner']);
$wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie',
    'wedding_date' => '2027-05-12', 'wedding_time' => '16:30', 'ceremony_venue' => 'The Garden Chapel',
    'reception_venue' => 'The Orchard Pavilion', 'public_slug' => 'synthetic-editor-preview']);
$result = ['email' => $owner->email, 'path' => '/accounts/'.$account->id.'/weddings/'.$wedding->id.'/cms-preview'];
if (getenv('CMS_PREVIEW_PERSONAL') === '1') {
    if (config('cms_editor.publishing_enabled') !== true) {
        throw new RuntimeException('Personal fixture requires controlled publication.');
    }
    $document = CmsEditorPreview::document();
    $document['content']['sections'][3]['data']['paragraphs'] = ['A story saved in our published invitation.'];
    app(CmsDraft::class)->save($owner, $wedding, $document, 0);
    $group = InvitationGroup::factory()->for($wedding)->create(['label' => 'Santos Family', 'contact_email' => 'private-family@example.test']);
    $guests = [];
    foreach (['Maya Santos', 'Nico Santos', 'Lia Santos', 'Mateo Santos'] as $name) {
        $guests[] = Guest::factory()->for($group)->create(['name' => $name]);
    }
    $links = app(PersonalInvitationLink::class);
    $representative = $links->execute($owner, $wedding, $group, $guests[0], 'generate', 0, 0)['token'];
    $independent = $links->execute($owner, $wedding, $group, $guests[2], 'generate', 0, 0)['token'];
    app(RsvpDelegationManager::class)->assign($owner, $wedding, $group, $guests[1], $guests[0], [$guests[1]->id => 0, $guests[0]->id => 1], 0);
    $publication = app(WeddingPublication::class);
    $state = $publication->summary($wedding->fresh());
    $publication->publish($wedding, ['draft_version' => $state['draftVersion'], 'publication_version' => $state['publicationVersion'], 'snapshot_token' => $state['snapshotToken']], $owner);
    $result['personal'] = ['representativeUrl' => app(RsvpMode::class)->invitationUrl($representative),
        'independentUrl' => app(RsvpMode::class)->invitationUrl($independent), 'groupId' => $group->id,
        'guests' => array_map(fn (Guest $guest): array => ['id' => $guest->id, 'name' => $guest->name], $guests)];
}
echo json_encode($result);
