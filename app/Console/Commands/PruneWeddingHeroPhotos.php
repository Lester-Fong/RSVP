<?php

namespace App\Console\Commands;

use App\Services\WeddingHeroPhotoProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneWeddingHeroPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wedding-photos:prune {--delete : Delete eligible orphaned photos; otherwise perform a dry run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find unreferenced wedding hero photos older than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(WeddingHeroPhotoProcessor $photos): int
    {
        $count = 0;
        foreach (Storage::disk(WeddingHeroPhotoProcessor::DISK)->allFiles('accounts') as $path) {
            if (! preg_match('~\Aaccounts/(\d+)/weddings/(\d+)/hero/~', $path, $matches)) {
                continue;
            }
            if ($photos->deleteIfUnreferenced((int) $matches[1], (int) $matches[2], WeddingHeroPhotoProcessor::DISK, $path, now()->subDay()->timestamp, (bool) $this->option('delete'))) {
                $count++;
            }
        }
        $this->info(($this->option('delete') ? 'Deleted' : 'Would delete').' '.$count.' orphaned wedding photo(s).');

        return self::SUCCESS;
    }
}
