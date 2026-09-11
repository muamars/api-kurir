<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupYearlyShipments extends Command
{
    protected $signature = 'shipments:backup-yearly
                            {--year= : Year to backup (defaults to previous year)}
                            {--dry-run : Show what would be backed up without doing it}';

    protected $description = 'Backup shipments from previous year to shipment_backup table';

    public function handle(): int
    {
        $year = $this->option('year') ?? (date('Y') - 1);
        $dryRun = $this->option('dry-run');

        $this->info("Backing up shipments for year: {$year}");

        $count = DB::table('shipments')
            ->where('shipment_id', 'LIKE', "SPJ-{$year}%")
            ->count();

        if ($count === 0) {
            $this->info("No shipments found for year {$year}. Nothing to backup.");
            return self::SUCCESS;
        }

        $this->info("Found {$count} shipments to backup.");

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes made.');
            return self::SUCCESS;
        }

        if (!$this->confirm("Proceed with backup? This will copy {$count} shipments to shipment_backup.")) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($year) {
            $now = now();

            // Copy shipments to backup table in chunks to avoid memory issues
            DB::table('shipments')
                ->where('shipment_id', 'LIKE', "SPJ-{$year}%")
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($now, $year) {
                    $inserts = $rows->map(fn ($row) => [
                        'original_id'                  => $row->id,
                        'shipment_id'                  => $row->shipment_id,
                        'created_by'                   => $row->created_by,
                        'approved_by'                  => $row->approved_by,
                        'assigned_driver_id'           => $row->assigned_driver_id,
                        'approved_at'                  => $row->approved_at,
                        'status'                       => $row->status,
                        'notes'                        => $row->notes,
                        'courier_notes'                => $row->courier_notes ?? null,
                        'priority'                     => $row->priority,
                        'deadline'                     => $row->deadline,
                        'scheduled_delivery_datetime'  => $row->scheduled_delivery_datetime ?? null,
                        'surat_pengantar_kerja'        => $row->surat_pengantar_kerja,
                        'attachment_path'              => $row->attachment_path ?? null,
                        'category_id'                  => $row->category_id ?? null,
                        'vehicle_type_id'              => $row->vehicle_type_id ?? null,
                        'division_id'                  => $row->division_id ?? null,
                        'tugas_pengiriman_id'          => $row->tugas_pengiriman_id ?? null,
                        'shipping_cost'                => $row->shipping_cost ?? null,
                        'vehicle_used'                 => $row->vehicle_used ?? null,
                        'completion_photo'             => $row->completion_photo ?? null,
                        'completed_at'                 => $row->completed_at ?? null,
                        'completed_by'                 => $row->completed_by ?? null,
                        'cancelled_by'                 => $row->cancelled_by ?? null,
                        'cancelled_at'                 => $row->cancelled_at ?? null,
                        'cancel_reason'                => $row->cancel_reason ?? null,
                        'created_at'                   => $row->created_at,
                        'updated_at'                   => $row->updated_at,
                        'backup_year'                  => (int) $year,
                        'backed_up_at'                 => $now,
                    ])->toArray();

                    DB::table('shipment_backup')->insert($inserts);
                });
        });

        $this->info("Backup complete. {$count} shipments copied to shipment_backup for year {$year}.");
        $this->info('Note: Original shipments are NOT deleted. Delete them manually if needed after verifying backup.');

        return self::SUCCESS;
    }
}
