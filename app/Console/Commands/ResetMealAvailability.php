<?php

namespace App\Console\Commands;

use App\Http\Services\MenuService;
use Illuminate\Console\Command;

class ResetMealAvailability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menu:reset-availability';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset daily meal availability quantities to their initial values';

    public function __construct(
        private MenuService $menuService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Resetting meal availability...');

        try {
            $this->menuService->resetDailyQuantities();
            $this->info('Meal availability reset successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to reset meal availability: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
