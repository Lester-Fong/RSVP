<?php

namespace App\Http\Controllers;

use App\Http\Requests\CmsEditorPreviewRequest;
use App\Http\Requests\SaveCmsDraftRequest;
use App\Models\Account;
use App\Models\Wedding;
use App\Services\CmsDraft;
use App\Services\CmsEditorPreview;
use App\Services\CmsV2Contract;
use App\Services\CmsV2Document;
use App\Services\EnchantedWebsite;
use App\Services\UploadWeddingImage;
use App\Services\WebsiteDesignRegistry;
use App\Services\WeddingImageAssetStorage;
use App\Services\WeddingImageResolver;
use App\Services\WeddingPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CmsEditorPreviewController extends Controller
{
    public function show(Request $request, Account $account, Wedding $wedding, WebsiteDesignRegistry $designs, CmsDraft $drafts, WeddingPublication $publication): \Inertia\Response
    {
        Gate::authorize('update', $wedding);

        $saved = $drafts->read($request->user(), $wedding);
        abort_unless($saved['document']['template_key'] === 'enchanted-editorial', 409);

        return Inertia::render('weddings/CmsEditorPreview', [
            'wedding' => $saved['wedding']->presentationData(), 'initialDocument' => $saved['document'],
            'draftVersion' => $saved['draft_version'], 'timeZone' => $saved['wedding']->timezone,
            'publishingEnabled' => EnchantedWebsite::enabled(),
            'publication' => $publication->summary($saved['wedding']),
            'initialImages' => $this->resolvedImages($saved['images'], $account, $wedding),
            'limits' => CmsEditorPreview::limits(), 'csrfToken' => csrf_token(),
            'capabilities' => collect($designs->templates())->filter(fn ($template): bool => in_array(2, $template->schemaVersions, true))
                ->mapWithKeys(fn ($template): array => [$template->key => $template->sectionTypes])->all(),
            'endpoints' => ['publish' => route('accounts.weddings.publish', [$account, $wedding], false),
                'images' => route('accounts.weddings.cms-preview.images', [$account, $wedding], false),
                'upload' => route('accounts.weddings.cms-preview.upload', [$account, $wedding], false),
                'validate' => route('accounts.weddings.cms-preview.validate', [$account, $wedding], false),
                'draft' => route('accounts.weddings.cms-preview.draft', [$account, $wedding], false),
                'preview' => route('accounts.weddings.cms-preview.saved-preview', [$account, $wedding], false)],
            'overviewUrl' => route('accounts.weddings.show', [$account, $wedding], false),
        ]);
    }

    public function draft(Request $request, Account $account, Wedding $wedding, CmsDraft $drafts): JsonResponse
    {
        $saved = $drafts->read($request->user(), $wedding);

        return response()->json(['document' => $saved['document'], 'draft_version' => $saved['draft_version']]);
    }

    public function save(SaveCmsDraftRequest $request, Account $account, Wedding $wedding, CmsDraft $drafts, WeddingPublication $publication): JsonResponse
    {
        abort_unless($request->validated('document.template_key') === 'enchanted-editorial', 409);

        $saved = $drafts->save($request->user(), $wedding, $request->validated('document'), $request->integer('draft_version'));

        return response()->json($saved + ['publication' => $publication->summary($wedding->fresh())]);
    }

    public function savedPreview(Request $request, Account $account, Wedding $wedding, CmsDraft $drafts, WebsiteDesignRegistry $designs): \Inertia\Response
    {
        $saved = $drafts->read($request->user(), $wedding);
        abort_unless($saved['draft_version'] > 0, 404);
        abort_unless($saved['document']['template_key'] === 'enchanted-editorial', 409);

        return Inertia::render('weddings/CmsSavedPreview', [
            'initialDocument' => $saved['document'], 'wedding' => $saved['wedding']->presentationData(),
            'timeZone' => $saved['wedding']->timezone, 'initialImages' => $this->resolvedImages($saved['images'], $account, $wedding),
            'supported' => $designs->template($saved['document']['template_key'], $saved['document']['template_version'], 2)->sectionTypes,
            'editorUrl' => route('accounts.weddings.cms-preview.show', [$account, $wedding], false),
        ]);
    }

    /** @param array<string, array<string, int|string>> $images
     * @return list<array<string, int|string>>
     */
    private function resolvedImages(array $images, Account $account, Wedding $wedding): array
    {
        return array_values(array_map(fn (array $image): array => $image + [
            'preview_url' => route('accounts.weddings.cms-preview.image', [$account, $wedding, $image['id']], false),
        ], $images));
    }

    public function images(Request $request, Account $account, Wedding $wedding, WeddingImageAssetStorage $storage): JsonResponse
    {
        Gate::authorize('update', $wedding);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:10000']]);
        $images = $wedding->mediaAssets()->orderByDesc('created_at')->orderBy('id')->paginate(12);

        return response()->json(['images' => $images->map(fn ($asset): array => $storage->metadata($asset) + [
            'preview_url' => route('accounts.weddings.cms-preview.image', [$account, $wedding, $asset->id], false),
        ])->values(), 'page' => $images->currentPage(), 'lastPage' => $images->lastPage(), 'total' => $images->total()]);
    }

    public function upload(CmsEditorPreviewRequest $request, Account $account, Wedding $wedding, UploadWeddingImage $upload): JsonResponse
    {
        $asset = $upload->upload($request->user(), $wedding, $request->file('image'));

        return response()->json(['image' => $asset + [
            'preview_url' => route('accounts.weddings.cms-preview.image', [$account, $wedding, $asset['id']], false),
        ]], 201);
    }

    public function image(Request $request, Account $account, Wedding $wedding, string $asset, WeddingImageResolver $resolver): Response
    {
        try {
            $bytes = $resolver->preview($request->user(), $wedding, $asset);
        } catch (ValidationException) {
            abort(404);
        }

        return response($bytes, 200, ['Content-Type' => 'image/webp', 'Content-Disposition' => 'inline']);
    }

    public function validateContent(CmsEditorPreviewRequest $request, Account $account, Wedding $wedding,
        CmsV2Contract $contract, WeddingImageResolver $resolver): JsonResponse
    {
        $document = CmsV2Document::fromArray($request->validated('document'), $contract);
        $resolver->validateDocument($request->user(), $wedding, $document);
        $slots = $request->validated('slots');
        foreach ($slots as $reference) {
            if ($reference !== null) {
                $resolver->resolve($request->user(), $wedding, $reference);
            }
        }

        return response()->json(['document' => $document->toArray(), 'projection' => $document->publicContent()]);
    }
}
