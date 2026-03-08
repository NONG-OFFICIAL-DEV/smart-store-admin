<?php

namespace App\Console\Commands;

use App\Models\Table;
use Illuminate\Console\Command;

class GenerateTableQrCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'app:generate-table-qr-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    // protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    protected $signature   = 'tables:generate-qr {--force : Regenerate even if QR exists}';
    protected $description = 'Generate QR codes for all tables';

    public function handle()
    {
        $query = Table::with('branch')
            ->when(
                !$this->option('force'),
                fn($q) =>
                $q->whereNull('qr_code')
            );

        $tables = $query->get();

        $this->info("Generating QR codes for {$tables->count()} tables...");

        $bar = $this->output->createProgressBar($tables->count());

        foreach ($tables as $table) {
            if (!$table->branch?->slug) {
                $this->warn("  Skipping table {$table->table_number} — branch has no slug");
                $bar->advance();
                continue;
            }

            $table->generateQrCode();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done!');
    }
}
