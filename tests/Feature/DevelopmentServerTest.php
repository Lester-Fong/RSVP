<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
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
        $cachePrefix = sys_get_temp_dir().'/rsvp-runtime-test-'.Str::uuid();
        $process = new Process([PHP_BINARY, '-d', 'upload_max_filesize=2M', '-d', 'post_max_size=3M', '-d', 'memory_limit=128M',
            base_path('artisan'), 'serve', '--host=127.0.0.1', '--port='.$port, '--tries=0', '--no-reload', '--no-interaction'],
            base_path(), ['APP_CONFIG_CACHE' => $cachePrefix.'-config.php',
                'APP_ROUTES_CACHE' => $cachePrefix.'-routes.php']);
        $process->setTimeout(15);
        $probe = <<<'PHP'
<?php
header('Content-Type: application/json');
echo json_encode(['upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size'),
    'memory' => ini_get('memory_limit'), 'exif' => extension_loaded('exif'),
    'sqlite' => extension_loaded('pdo_sqlite'), 'gd' => extension_loaded('gd'),
    'error' => $_FILES['hero_photo']['error'] ?? null, 'size' => $_FILES['hero_photo']['size'] ?? null]);
PHP;
        $handle = fopen($fixture, 'x');
        $this->assertIsResource($handle);

        try {
            fwrite($handle, $probe);
            fclose($handle);
            $process->start();
            try {
                $started = $process->waitUntil(fn (string $type, string $output): bool => str_contains($process->getOutput(), 'Server running on'));
            } catch (ProcessTimedOutException $exception) {
                throw new RuntimeException('Development server startup timed out. '.$this->processDiagnostics($process), 0, $exception);
            }
            $this->assertTrue($started, $this->processDiagnostics($process));
            $socket = @stream_socket_client('tcp://127.0.0.1:'.$port, $errorCode, $errorMessage, 1);
            $this->assertIsResource($socket, $errorMessage.' '.$this->processDiagnostics($process));
            fclose($socket);
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
                'sqlite' => true, 'gd' => true,
                'error' => UPLOAD_ERR_OK, 'size' => strlen($png)], $result);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            $process->stop();
            unlink($fixture);
        }
    }

    private function processDiagnostics(Process $process): string
    {
        return sprintf('PHP: %s; ini: %s; scanned: %s; exit: %s; stdout: %s; stderr: %s',
            PHP_BINARY, php_ini_loaded_file() ?: 'none', php_ini_scanned_files() ?: 'none',
            var_export($process->getExitCode(), true), $process->getOutput(), $process->getErrorOutput());
    }
}
