<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DevelopmentServerTest extends TestCase
{
    public function test_standard_serve_configures_the_receiving_php_process_and_accepts_an_image_over_two_mib(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertIsResource($listener, $errorMessage);
        $port = (int) substr(strrchr(stream_socket_get_name($listener, false), ':'), 1);
        fclose($listener);
        $name = '_runtime_test_'.Str::uuid().'.php';
        $fixture = public_path($name);
        $process = new Process([PHP_BINARY, '-d', 'upload_max_filesize=2M', '-d', 'post_max_size=3M', '-d', 'memory_limit=128M',
            base_path('artisan'), 'serve', '--host=127.0.0.1', '--port='.$port, '--tries=0', '--no-reload', '--no-interaction'],
            base_path(), ['PHP_INI_SCAN_DIR' => '', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/rsvp-runtime-test-no-config.php',
                'APP_ROUTES_CACHE' => sys_get_temp_dir().'/rsvp-runtime-test-no-routes.php']);
        $probe = <<<'PHP'
<?php
header('Content-Type: application/json');
echo json_encode(['upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size'),
    'memory' => ini_get('memory_limit'), 'exif' => extension_loaded('exif'),
    'error' => $_FILES['hero_photo']['error'] ?? null, 'size' => $_FILES['hero_photo']['size'] ?? null]);
PHP;
        $handle = fopen($fixture, 'x');
        fwrite($handle, $probe);
        fclose($handle);

        try {
            $process->start();
            $process->waitUntil(fn (string $type, string $output): bool => str_contains($process->getOutput(), 'Server running on'));
            $image = imagecreatetruecolor(800, 980);
            ob_start();
            imagepng($image, null, 0);
            $png = ob_get_clean();
            $this->assertGreaterThan(2 * 1024 * 1024, strlen($png));
            $boundary = 'rsvp-'.Str::uuid();
            $body = '--'.$boundary."\r\nContent-Disposition: form-data; name=\"hero_photo\"; filename=\"photo.png\"\r\nContent-Type: image/png\r\n\r\n".$png."\r\n--".$boundary."--\r\n";
            $context = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 10,
                'header' => "Content-Type: multipart/form-data; boundary=$boundary\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n", 'content' => $body]]);
            $response = file_get_contents('http://127.0.0.1:'.$port.'/'.$name, false, $context);
            if ($response === false) {
                throw new RuntimeException('Development server did not respond.');
            }
            $result = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(['upload' => '5M', 'post' => '8M', 'memory' => '256M', 'exif' => true,
                'error' => UPLOAD_ERR_OK, 'size' => strlen($png)], $result);
        } finally {
            $process->stop();
            unlink($fixture);
        }
    }
}
