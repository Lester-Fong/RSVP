<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

try {
    $database = getenv('CMS_MYSQL_TEST_DATABASE') ?: '';

    if (getenv('APP_ENV') !== 'testing' || ! preg_match('/\Awedding_rsvp_cms_test(?:_[a-z0-9_]+)?\z/D', $database) || getenv('DB_DATABASE') !== $database) {
        throw new RuntimeException('The worker requires an explicitly selected disposable MySQL test database.');
    }

    $payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $application = require __DIR__.'/../../bootstrap/app.php';
    $kernel = $application->make(Kernel::class);
    $kernel->bootstrap();

    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== $database || config('database.connections.mysql.url') !== null) {
        throw new RuntimeException('The worker database configuration does not match the disposable test target.');
    }

    if (DB::selectOne('SELECT DATABASE() AS name')->name !== $database) {
        throw new RuntimeException('The connected database does not match the disposable test target.');
    }

    DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
    Auth::guard('web')->setUser(User::query()->findOrFail($payload['owner_id']));
    $url = '/accounts/'.$payload['account_id'].'/weddings/'.$payload['wedding_id'].'/invitation';
    if (isset($payload['photo'])) {
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

    fwrite(STDOUT, 'READY '.DB::selectOne('SELECT CONNECTION_ID() AS id')->id.PHP_EOL);
    fflush(STDOUT);
    $response = $kernel->handle($request);
    fwrite(STDOUT, 'RESULT '.json_encode([
        'status' => $response->getStatusCode(),
        'body' => json_decode($response->getContent(), true),
    ], JSON_THROW_ON_ERROR).PHP_EOL);
    $kernel->terminate($request, $response);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.PHP_EOL);
    exit(1);
}
