<?php

namespace App\Console\Commands;

use App\Services\ShipmentArchiveService;
use Illuminate\Console\Command;

class ArchiveShipments extends Command
{
    protected $signature = 'shipments:archive
                            {--days=30 : Arsipkan shipments completed/cancelled yang lebih dari N hari}
                            {--dry-run : Tampilkan jumlah yang akan diarsip tanpa eksekusi}';

    protected $description = 'Pindahkan shipments completed/cancelled lama ke tabel shipment_history';

    public function handle(ShipmentArchiveService $service): int
    {
        $days   = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE'));
        $this->info("Mengarsipkan shipments completed/cancelled yang sudah lebih dari {$days} hari...");

        if ($dryRun) {
            $count = \App\Models\Shipment::active()
                ->whereIn('status', ['completed', 'cancelled'])
                ->where('updated_at', '<', now()->subDays($days))
                ->count();

            $this->warn("[DRY RUN] {$count} shipments akan diarsipkan. Tidak ada perubahan yang dibuat.");
            return self::SUCCESS;
        }

        $stats = $service->archiveBatch($days);

        $this->info("Selesai.");
        $this->table(['Berhasil', 'Gagal'], [[$stats['processed'], $stats['failed']]]);

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
