<?php

namespace App\Extensions\TitanNova\System\Console\Commands;

use App\Extensions\TitanNova\System\Database\Seeders\TitanNovaDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SeedDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'titan-nova:seed-demo-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed a demo Titan Nova agent and task using the TitanNovaAgent tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Titan Nova demo task seeding...');
        $this->newLine();

        Log::info('titan-nova:seed-demo-data started');

        $seeder = new TitanNovaDemoSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('✅ Demo data seeding completed!');
        Log::info('titan-nova:seed-demo-data finished');

        return CommandAlias::SUCCESS;
    }
}
