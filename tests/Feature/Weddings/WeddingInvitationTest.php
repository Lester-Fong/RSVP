<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeddingInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_opens_editor_with_defaults_without_creating_a_draft(): void
    {
        [$owner, $wedding] = $this->ownedWedding();

        $this->actingAs($owner)->get($this->editorUrl($wedding))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('weddings/InvitationEditor')
                ->where('weddingId', $wedding->id)
                ->where('draftVersion', 0)
                ->where('customization.introduction_message', 'Joyfully invite you to celebrate their wedding')
                ->where('customization.closing_message', 'We look forward to celebrating with you')
                ->where('customization.sections', ['ceremony' => true, 'reception' => true, 'closing' => true])
                ->where('wedding.ceremony_venue', 'Manila Cathedral')
                ->has('wedding', 6));

        $this->get(route('accounts.weddings.preview', [$wedding->account_id, $wedding]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('accountId', $wedding->account_id)->where('weddingId', $wedding->id)
                ->where('customization.introduction_message', 'Joyfully invite you to celebrate their wedding')
                ->where('customization.sections.closing', true));
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_messages_and_visibility_persist_on_editor_reload_and_standalone_preview_without_changing_wedding_facts(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $originalWedding = $wedding->fresh()->getAttributes();
        $content = $this->content();

        $this->actingAs($owner)->put($this->updateUrl($wedding), ['content' => $content, 'draft_version' => 0])
            ->assertSessionHasNoErrors()->assertRedirect($this->editorUrl($wedding));

        $this->assertDatabaseHas('website_drafts', ['wedding_id' => $wedding->id, 'schema_version' => 1, 'lock_version' => 1]);
        $this->assertSame($content, $wedding->websiteDraft()->sole()->content);
        $this->assertSame($originalWedding, $wedding->fresh()->getAttributes());
        $this->get($this->editorUrl($wedding))->assertInertia(fn (Assert $page) => $page
            ->where('customization', $content)->where('draftVersion', 1));
        $this->get(route('accounts.weddings.preview', [$wedding->account_id, $wedding]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customization', $content)
                ->where('wedding.reception_venue', 'Intramuros Hall'));

        $content['sections'] = ['ceremony' => true, 'reception' => true, 'closing' => true];
        $this->put($this->updateUrl($wedding), ['content' => $content, 'draft_version' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('website_drafts', 1);
        $this->get(route('accounts.weddings.preview', [$wedding->account_id, $wedding]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customization.sections.reception', true)
                ->where('wedding.reception_venue', 'Intramuros Hall'));
    }

    public function test_each_wedding_has_an_independent_draft_even_within_one_account(): void
    {
        [$owner, $first] = $this->ownedWedding();
        $second = $first->account->weddings()->create([
            'partner_one_name' => 'Second', 'partner_two_name' => 'Couple', 'public_slug' => 'second-couple',
        ]);
        $this->actingAs($owner)->put($this->updateUrl($first), ['content' => $this->content(), 'draft_version' => 0])->assertSessionHasNoErrors();
        $this->get($this->editorUrl($second))->assertInertia(fn (Assert $page) => $page
            ->where('draftVersion', 0)
            ->where('customization.introduction_message', 'Joyfully invite you to celebrate their wedding'));

        $otherContent = $this->content();
        $otherContent['introduction_message'] = 'Welcome to our second wedding';
        $this->put($this->updateUrl($second), ['content' => $otherContent, 'draft_version' => 0])->assertSessionHasNoErrors();
        $this->assertSame($this->content(), $first->websiteDraft()->sole()->content);
        $this->assertSame($otherContent, $second->websiteDraft()->sole()->content);
    }

    public function test_editor_and_save_require_authentication(): void
    {
        [, $wedding] = $this->ownedWedding();

        $this->get($this->editorUrl($wedding))->assertRedirect(route('login'));
        $this->put($this->updateUrl($wedding), ['content' => $this->content(), 'draft_version' => 0])->assertRedirect(route('login'));
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_another_account_cannot_read_or_overwrite_a_saved_draft(): void
    {
        [, $wedding] = $this->ownedWedding();
        [$attacker] = $this->ownedWedding();
        $draft = WebsiteDraft::factory()->for($wedding)->create();

        $this->actingAs($attacker)->get($this->editorUrl($wedding))->assertForbidden();
        $this->put($this->updateUrl($wedding), ['content' => $this->content(), 'draft_version' => 1])->assertForbidden();

        $this->assertSame(WebsiteDraft::defaultContent(), $draft->fresh()->content);
        $this->assertSame(1, $draft->fresh()->lock_version);
    }

    public function test_non_owner_member_cannot_edit_or_save_a_draft(): void
    {
        [, $wedding] = $this->ownedWedding();
        $member = User::factory()->create();
        $wedding->account->users()->attach($member, ['role' => 'viewer']);

        $this->actingAs($member)->get($this->editorUrl($wedding))->assertForbidden();
        $this->put($this->updateUrl($wedding), ['content' => $this->content(), 'draft_version' => 0])->assertForbidden();
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_mismatched_nested_account_returns_404_for_editor_and_save(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $otherAccount = Account::create(['name' => 'Another workspace']);
        $otherAccount->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)->get(route('accounts.weddings.invitation.edit', [$otherAccount, $wedding]))->assertNotFound();
        $this->put(route('accounts.weddings.invitation.update', [$otherAccount, $wedding]), [
            'content' => $this->content(), 'draft_version' => 0,
        ])->assertNotFound();
        $this->assertDatabaseCount('website_drafts', 0);
    }

    #[DataProvider('invalidDrafts')]
    public function test_invalid_draft_is_rejected_without_changing_saved_content(string $key, mixed $value, string $error): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $draft = WebsiteDraft::factory()->for($wedding)->create();
        $payload = ['content' => $this->content(), 'draft_version' => 1];
        data_set($payload, $key, $value);

        $this->actingAs($owner)->putJson($this->updateUrl($wedding), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);

        $this->assertSame(WebsiteDraft::defaultContent(), $draft->fresh()->content);
        $this->assertSame(1, $draft->fresh()->lock_version);
    }

    public static function invalidDrafts(): array
    {
        return [
            'introduction required' => ['content.introduction_message', '', 'content.introduction_message'],
            'closing required' => ['content.closing_message', '', 'content.closing_message'],
            'introduction too long' => ['content.introduction_message', str_repeat('a', 501), 'content.introduction_message'],
            'closing too long' => ['content.closing_message', str_repeat('a', 501), 'content.closing_message'],
            'introduction wrong type' => ['content.introduction_message', [], 'content.introduction_message'],
            'closing wrong type' => ['content.closing_message', 123, 'content.closing_message'],
            'script rejected' => ['content.introduction_message', '<script>alert(1)</script>', 'content.introduction_message'],
            'markup rejected' => ['content.closing_message', '<b>Welcome</b>', 'content.closing_message'],
            'ceremony wrong type' => ['content.sections.ceremony', 'true', 'content.sections.ceremony'],
            'reception wrong type' => ['content.sections.reception', 'false', 'content.sections.reception'],
            'closing wrong type' => ['content.sections.closing', 1, 'content.sections.closing'],
            'unknown section' => ['content.sections.gallery', true, 'content.sections'],
            'unknown content field' => ['content.custom_css', 'body{}', 'content'],
            'unknown root field' => ['template', 'another-design', 'template'],
            'wedding ownership' => ['wedding_id', 999, 'wedding_id'],
            'account ownership' => ['account_id', 999, 'account_id'],
            'schema controlled by server' => ['schema_version', 9, 'schema_version'],
            'negative version' => ['draft_version', -1, 'draft_version'],
            'non integer version' => ['draft_version', 1.5, 'draft_version'],
        ];
    }

    public function test_missing_configuration_keys_are_rejected_without_creating_a_draft(): void
    {
        [$owner, $wedding] = $this->ownedWedding();

        $this->actingAs($owner)->putJson($this->updateUrl($wedding), [])->assertUnprocessable()->assertJsonValidationErrors([
            'draft_version', 'content.introduction_message', 'content.closing_message',
            'content.sections.ceremony', 'content.sections.reception', 'content.sections.closing',
        ]);
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_plain_text_validation_gives_an_actionable_error(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $content = $this->content();
        $content['introduction_message'] = '<img src=x onerror=alert(1)>';

        $response = $this->actingAs($owner)->putJson($this->updateUrl($wedding), ['content' => $content, 'draft_version' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('content.introduction_message');
        $this->assertSame('Use plain text without HTML or angle brackets.', $response->json('errors')['content.introduction_message'][0]);
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_stale_first_save_and_stale_update_cannot_overwrite_a_newer_draft(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        WebsiteDraft::factory()->for($wedding)->create();

        $this->actingAs($owner)->put($this->updateUrl($wedding), ['content' => $this->content(), 'draft_version' => 0])
            ->assertSessionHasErrors('draft_version');
        $this->put($this->updateUrl($wedding), ['content' => $this->content(), 'draft_version' => 1])->assertSessionHasNoErrors();
        $this->put($this->updateUrl($wedding), ['content' => WebsiteDraft::defaultContent(), 'draft_version' => 1])
            ->assertSessionHasErrors('draft_version');
        $this->assertSame($this->content(), $wedding->websiteDraft()->sole()->content);
        $this->assertDatabaseCount('website_drafts', 1);
    }

    #[DataProvider('unsupportedSchemaVersions')]
    public function test_unsupported_stored_schema_is_not_rendered_or_overwritten(int $schemaVersion): void
    {
        config(['app.debug' => false]);
        [$owner, $wedding] = $this->ownedWedding();
        $draft = WebsiteDraft::factory()->for($wedding)->create([
            'schema_version' => $schemaVersion,
            'content' => ['future_private_field' => 'Do not reveal this content'],
        ]);
        $original = $draft->fresh()->getAttributes();

        $this->actingAs($owner)->get($this->editorUrl($wedding))
            ->assertConflict()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText('Your saved draft has not been changed.')
            ->assertDontSee('Do not reveal this content');
        $this->getJson(route('accounts.weddings.preview', [$wedding->account_id, $wedding]))
            ->assertConflict()
            ->assertExactJson(['message' => 'This invitation draft uses an unsupported content version. Your saved draft has not been changed. Please contact support.']);
        $this->putJson($this->updateUrl($wedding), ['content' => $this->content(), 'draft_version' => 1])
            ->assertConflict()->assertExactJson(['message' => 'This invitation draft uses an unsupported content version. Your saved draft has not been changed. Please contact support.']);

        $this->assertSame($original, $draft->fresh()->getAttributes());
    }

    /** @return array<string, array{int}> */
    public static function unsupportedSchemaVersions(): array
    {
        return ['zero' => [0], 'future version' => [2], 'unknown version' => [65535]];
    }

    public function test_schema_changed_since_editor_loaded_returns_a_controlled_error_without_overwriting(): void
    {
        [$owner, $wedding] = $this->ownedWedding();
        $draft = WebsiteDraft::factory()->for($wedding)->create();
        $this->actingAs($owner)->get($this->editorUrl($wedding))->assertOk();
        $draft->update(['schema_version' => 2, 'content' => ['future_content' => 'Preserve me']]);
        $original = $draft->fresh()->getAttributes();

        $this->from($this->editorUrl($wedding))->put($this->updateUrl($wedding), [
            'content' => $this->content(), 'draft_version' => 1,
        ])->assertConflict()->assertSeeText('Your saved draft has not been changed.');

        $this->assertSame($original, $draft->fresh()->getAttributes());
    }

    public function test_unsupported_schema_error_does_not_bypass_tenant_authorization(): void
    {
        [, $wedding] = $this->ownedWedding();
        [$otherOwner] = $this->ownedWedding();
        $draft = WebsiteDraft::factory()->for($wedding)->create(['schema_version' => 2]);
        $original = $draft->fresh()->getAttributes();

        $this->actingAs($otherOwner)->getJson($this->editorUrl($wedding))->assertForbidden();
        $this->getJson(route('accounts.weddings.preview', [$wedding->account_id, $wedding]))->assertForbidden();
        $this->putJson($this->updateUrl($wedding), ['content' => $this->content(), 'draft_version' => 1])->assertForbidden();

        $this->assertSame($original, $draft->fresh()->getAttributes());
    }

    /** @return array{User, Wedding} */
    private function ownedWedding(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Wedding workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create([
            'partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie',
            'public_slug' => fake()->unique()->slug(),
            'wedding_date' => '2027-05-12', 'wedding_time' => '16:30:00',
            'ceremony_venue' => 'Manila Cathedral', 'reception_venue' => 'Intramuros Hall',
        ]);

        return [$owner, $wedding];
    }

    private function editorUrl(Wedding $wedding): string
    {
        return route('accounts.weddings.invitation.edit', [$wedding->account_id, $wedding]);
    }

    private function updateUrl(Wedding $wedding): string
    {
        return route('accounts.weddings.invitation.update', [$wedding->account_id, $wedding]);
    }

    /** @return array<string, mixed> */
    private function content(): array
    {
        return [
            'introduction_message' => "Join our celebration\nWith all our love & joy",
            'closing_message' => "See you soon\nAlex & Jamie",
            'sections' => ['ceremony' => false, 'reception' => false, 'closing' => false],
        ];
    }
}
