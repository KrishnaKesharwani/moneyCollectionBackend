<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;


class MakeRepository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $repositoryPath = app_path("Repositories/{$name}.php");

        if (!File::exists($repositoryPath)) {
            File::ensureDirectoryExists(app_path('Repositories'));
            File::put($repositoryPath, $this->getStubContent($name));

            $this->info("Repository {$name} created successfully.");
        } else {
            $this->error("Repository {$name} already exists!");
        }
    }

    protected function getStubContent($name)
    {
        return "<?php

        namespace App\Repositories;

        class {$name} extends BaseRepository
        {
            // Add your repository methods here
        }";
    }
}
