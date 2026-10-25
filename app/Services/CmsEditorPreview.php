<?php

namespace App\Services;

use App\Models\Wedding;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class CmsEditorPreview
{
    public const HEADERS = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        'Cross-Origin-Resource-Policy' => 'same-origin'];

    public static function handles(Request $request): bool
    {
        return $request->is('accounts/*/weddings/*/cms-preview', 'accounts/*/weddings/*/cms-preview/*');
    }

    /** Template dispatch only; callers still authorize the wedding and enforce the editor boundary. */
    public static function shouldOpen(Wedding $wedding): bool
    {
        if (config('cms_editor.preview_enabled') !== true || ! app()->environment(['local', 'testing'])) {
            return false;
        }

        $draft = $wedding->websiteDraft;
        if ($draft !== null) {
            return $draft->schema_version === 2 && $draft->template_key === 'enchanted-editorial';
        }

        // An unconfigured website has no template choice to convert. Reads never create a draft.
        return $wedding->published_revision_id === null && ! $wedding->websiteRevisions()->exists();
    }

    public static function enforce(): void
    {
        abort_unless(config('cms_editor.preview_enabled') === true && app()->environment(['local', 'testing']), 404);
        abort_unless(Schema::hasTable('website_revision_media') && Schema::hasColumns('wedding_media_assets',
            ['id', 'wedding_id', 'disk', 'path', 'kind', 'mime_type', 'byte_size', 'width', 'height'])
            && Schema::hasColumns('website_drafts', ['template_key', 'template_version', 'lock_version', 'content'])
            && Schema::hasColumn('weddings', 'timezone'), 503);
    }

    /** @return array<string, int> */
    public static function limits(): array
    {
        return ['paragraphs' => CmsV2Contract::MAX_PARAGRAPHS, 'paragraphLength' => CmsV2Contract::MAX_PARAGRAPH_LENGTH,
            'milestones' => CmsV2Contract::MAX_MILESTONES, 'titleLength' => CmsV2Contract::MAX_TITLE_LENGTH,
            'descriptionLength' => CmsV2Contract::MAX_DESCRIPTION_LENGTH, 'galleryImages' => CmsV2Contract::MAX_GALLERY_IMAGES,
            'entourageGroups' => CmsV2Contract::MAX_ENTOURAGE_GROUPS, 'peoplePerGroup' => CmsV2Contract::MAX_PEOPLE_PER_GROUP,
            'people' => CmsV2Contract::MAX_ENTOURAGE_PEOPLE, 'nameLength' => CmsV2Contract::MAX_NAME_LENGTH,
            'guestGuideSwatches' => CmsV2Contract::MAX_GUEST_GUIDE_SWATCHES, 'guestGuideFaqs' => CmsV2Contract::MAX_GUEST_GUIDE_FAQS];
    }

    /** Code-owned starter copy, not invented customer biography or persisted draft content.
     * @return array<string, mixed>
     */
    public static function document(): array
    {
        $sections = [];
        foreach ([
            ['welcome', ['message' => 'Together with our families, we invite you to celebrate our wedding.']],
            ['event_details', ['events' => array_map(fn (string $kind): array => ['id' => (string) Str::uuid(),
                'kind' => $kind, 'address' => '', 'directions' => '', 'map_url' => null], ['ceremony', 'reception'])]],
            ['countdown', ['heading' => 'Until we celebrate together', 'subtitle' => 'We cannot wait to share this day with you.']],
            ['story', ['heading' => 'Our love story', 'paragraphs' => [], 'milestones' => []]],
            ['gallery', ['heading' => 'Our photographs', 'items' => []]],
            ['entourage', ['heading' => 'Together with our loved ones', 'groups' => []]],
            ['guest_guide', ['attire' => ['enabled' => false, 'dress_code' => 'Dress code to be announced', 'description' => '', 'swatches' => []],
                'gifts' => ['enabled' => false, 'heading' => 'Gifts', 'message' => '', 'url' => null],
                'faqs' => ['enabled' => true, 'items' => [
                    ['id' => (string) Str::uuid(), 'question' => 'Are my kids welcome?', 'answer' => 'Please check your invitation or contact the couple if you are unsure whether children are included.'],
                    ['id' => (string) Str::uuid(), 'question' => 'Can I bring a plus one?', 'answer' => 'Please refer to your invitation for the names invited, or contact the couple if you have a question.'],
                    ['id' => (string) Str::uuid(), 'question' => 'How do I RSVP?', 'answer' => 'If you received a personal invitation, please use its RSVP section. Contact the couple if you need help.'],
                ]]]],
            ['rsvp_information', ['message' => 'Please respond using your personal invitation link. We look forward to celebrating with you.']],
            ['closing', ['message' => 'We look forward to celebrating with you.']],
        ] as [$type, $data]) {
            $sections[] = ['id' => (string) Str::uuid(), 'type' => $type, 'enabled' => true, 'data' => $data];
        }

        return ['schema_version' => 2, 'template_key' => 'enchanted-editorial', 'template_version' => 1,
            'content' => ['theme' => ['palette' => 'blush-terracotta', 'font_pair' => 'editorial-serif'],
                'photo_slots' => ['hero' => null, 'details' => null, 'story' => null],
                'experience' => ['enabled' => false], 'sections' => $sections]];
    }

    public static function error(Throwable $exception, Request $request): Response
    {
        $status = match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $message = match ($status) {
            401 => 'Sign in again before continuing.',
            403, 404 => 'This editor or photograph is unavailable.',
            413 => 'Choose a JPEG, PNG or WebP photograph up to 5 MiB.',
            419 => 'Your session expired. Keep this tab open, sign in again in another tab, then retry here.',
            409 => 'This draft uses a different template or schema. It has been preserved; resolve the transition before continuing.',
            422 => 'Check the highlighted fields. Your local edits have not been saved as a draft.',
            429 => 'Please wait '.max(1, (int) ($headers['Retry-After'] ?? 60)).' seconds before trying again.',
            503 => 'This isolated editor requires its test database to be prepared.',
            default => 'This operation could not finish. Your local edits remain in this tab.',
        };

        return response()->json(['message' => $message,
            'errors' => $exception instanceof ValidationException ? $exception->errors() : []],
            $status, [...$headers, ...self::HEADERS]);
    }
}
