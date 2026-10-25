<?php

use App\Models\User;
use App\Models\WebsiteRevisionMedia;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';

$phase = 'environment guard';
try {
    $mysqlConfiguration = TestDatabaseGuard::disposableMySqlConfigurationFromProcess(workerConnection: true);
    $database = $mysqlConfiguration['database'] ?? '';

    if ($mysqlConfiguration === null || getenv('APP_ENV') !== 'testing'
        || getenv('DB_CONNECTION') !== 'mysql'
        || getenv('DB_DATABASE') !== $database
        || getenv('DB_HOST') !== $mysqlConfiguration['host']
        || getenv('DB_USERNAME') !== $mysqlConfiguration['username']
        || getenv('DB_PASSWORD') !== $mysqlConfiguration['password']) {
        throw new RuntimeException('The worker requires the explicit, isolated disposable MySQL test configuration.');
    }

    $payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $phase = 'application bootstrap';
    $application = require __DIR__.'/../../bootstrap/app.php';
    $kernel = $application->make(Kernel::class);
    $kernel->bootstrap();

    $phase = 'connection guard';
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== $database || config('database.connections.mysql.url') !== null) {
        throw new RuntimeException('The worker database configuration does not match the disposable test target.');
    }

    if (DB::selectOne('SELECT DATABASE() AS name')->name !== $database) {
        throw new RuntimeException('The connected database does not match the disposable test target.');
    }

    DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
    if (isset($payload['media_root'])) {
        $mediaRoot = realpath($payload['media_root']);
        if (! $mediaRoot || realpath(dirname($mediaRoot)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($mediaRoot), 'rsvp-cms-media-')) {
            throw new RuntimeException('The media worker requires an isolated temporary media directory.');
        }
        config(['filesystems.disks.wedding_media.root' => $mediaRoot]);
    }
    if (($payload['fail_binding'] ?? false) === true) {
        Event::listen('eloquent.created: '.WebsiteRevisionMedia::class, fn () => throw new RuntimeException('Synthetic binding rollback'));
    }
    $url = '/accounts/'.$payload['account_id'].'/weddings/'.$payload['wedding_id'].'/invitation';
    if (($payload['operation'] ?? 'draft') === 'link') {
        $request = Request::create('/accounts/'.$payload['account_id'].'/weddings/'.$payload['wedding_id'].'/invitation-groups/'.$payload['group_id'].'/rsvp-access/guests/'.$payload['guest_id'].'/credential/'.$payload['link_action'], 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], json_encode(['expectedVersion' => $payload['link_version'], 'expectedAccessVersion' => $payload['link_version']], JSON_THROW_ON_ERROR));
    } elseif (($payload['operation'] ?? 'draft') === 'publish') {
        $request = Request::create('/accounts/'.$payload['account_id'].'/weddings/'.$payload['wedding_id'].'/publish', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload['publication'], JSON_THROW_ON_ERROR));
    } elseif (($payload['operation'] ?? '') === 'cms_draft') {
        $request = Request::create('/accounts/'.$payload['account_id'].'/weddings/'.$payload['wedding_id'].'/cms-preview/draft', 'PUT', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document' => $payload['document'], 'draft_version' => $payload['draft_version']], JSON_THROW_ON_ERROR));
    } elseif (($payload['operation'] ?? '') === 'unpublish') {
        $request = Request::create('/accounts/'.$payload['account_id'].'/weddings/'.$payload['wedding_id'].'/unpublish', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], json_encode(['publication_version' => $payload['publication_version']], JSON_THROW_ON_ERROR));
    } elseif (($payload['operation'] ?? '') === 'media') {
        $request = Request::create('/w/'.$payload['slug'].'/media/'.$payload['asset'], 'GET', [], [], [], ['HTTP_ACCEPT' => 'image/webp']);
    } elseif (($payload['operation'] ?? '') === 'facts') {
        $request = Request::create('/accounts/'.$payload['account_id'].'/weddings/'.$payload['wedding_id'], 'PUT', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload['facts'], JSON_THROW_ON_ERROR));
    } elseif (isset($payload['photo'])) {
        $root = $payload['photo_root'];
        if (! is_dir($root) || realpath(dirname($root)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($root), 'rsvp-cms-photos-')) {
            throw new RuntimeException('Worker requires an isolated temporary photo directory.');
        }
        config(['filesystems.disks.wedding_photos.root' => $root]);
        $request = Request::create($url, 'PUT', [
            'content' => json_encode($payload['content'], JSON_THROW_ON_ERROR), 'draft_version' => $payload['draft_version'], 'hero_photo_action' => 'replace',
        ], [], ['hero_photo' => UploadedFile::fake()->createWithContent('worker.jpg', base64_decode($payload['photo'], true))], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'multipart/form-data',
        ]);
    } else {
        $request = Request::create($url, 'PUT', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], json_encode(['content' => $payload['content'], 'draft_version' => $payload['draft_version']], JSON_THROW_ON_ERROR));
    }

    $phase = 'owner resolution';
    $application->instance('request', $request);
    Auth::guard('web')->setUser(User::query()->findOrFail($payload['owner_id']));
    fwrite(STDOUT, 'READY '.DB::selectOne('SELECT CONNECTION_ID() AS id')->id.PHP_EOL);
    fflush(STDOUT);
    $response = $kernel->handle($request);
    $resultBody = json_decode($response->getContent(), true);
    if (($payload['operation'] ?? 'draft') === 'link' && is_array($resultBody)) {
        $member = collect($resultBody['group']['members'] ?? [])->firstWhere('guest.id', $payload['guest_id']);
        if ($member !== null) {
            $resultBody = ['status' => $member['link_status'], 'version' => $member['link_version']];
        }
    }
    fwrite(STDOUT, 'RESULT '.json_encode([
        'status' => $response->getStatusCode(),
        'body' => $resultBody,
    ], JSON_THROW_ON_ERROR).PHP_EOL);
    $kernel->terminate($request, $response);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['type' => $exception::class, 'phase' => $phase, 'code' => $exception->getCode()]).PHP_EOL);
    exit(1);
}
