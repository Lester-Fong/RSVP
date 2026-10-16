<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

class Serve extends ServeCommand
{
    /** @return array<int, string> */
    protected function serverCommand(): array
    {
        $command = parent::serverCommand();
        $settings = [
            '-d', 'upload_max_filesize=5M',
            '-d', 'post_max_size=8M',
            '-d', 'memory_limit=256M',
        ];

        if (! extension_loaded('exif')) {
            array_push($settings, '-d', 'extension=exif');
        }

        array_splice($command, 1, 0, $settings);

        return $command;
    }
}
