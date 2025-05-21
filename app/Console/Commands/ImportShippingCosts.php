<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportShippingCosts extends Command
{
    protected $signature = 'shipping:import {file} {courier}';
    protected $description = 'Import shipping costs from CSV in batches';

    public function handle()
    {
        // Increase memory and timeout for large CSVs
        ini_set('memory_limit', '512M');
        set_time_limit(600); // 10 minutes

        $file = $this->argument('file');
        $courier = $this->argument('courier');

        $this->info("Starting import for {$courier} from {$file}");

        $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
        if (!file_exists($file)) {
            $this->error("File {$file} not found!");
            Log::error("Import failed: File {$file} not found");
            return 1;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error("Failed to open {$file}!");
            Log::error("Import failed: Cannot open {$file}");
            return 1;
        }

        $header = fgetcsv($handle);
        if (count($header) !== 102) {
            $this->error("Invalid CSV header: expected 102 columns, got " . count($header));
            Log::error("Invalid CSV header: " . implode(',', array_slice($header, 0, 5)) . "...");
            fclose($handle);
            return 1;
        }
        $this->info("Header read: " . implode(',', array_slice($header, 0, 5)) . "...");

        $inserts = [];
        $rowCount = 0;
        $batchSize = 1000; // Insert every 1000 rows (~10 postcodes)

        while ($row = fgetcsv($handle)) {
            $rowCount++;
            if (count($row) !== 102) {
                $this->warn("Row {$rowCount} has " . count($row) . " columns, skipping");
                Log::warning("Row {$rowCount} has " . count($row) . " columns: " . implode(',', array_slice($row, 0, 5)) . "...");
                continue;
            }

            $this->info("Processing row {$rowCount}: Postcode {$row[0]}, Suburb {$row[1]}");
            for ($i = 1; $i <= 100; $i++) {
                $cost = str_replace('$', '', $row[$i + 1]);
                if ($cost === '' || $cost === null) {
                    $this->warn("Skipping weight_kg {$i} for postcode {$row[0]}: empty cost");
                    continue;
                }
                $inserts[] = [
                    'courier' => $courier,
                    'postcode' => $row[0],
                    'suburb' => $row[1],
                    'weight_kg' => $i,
                    'cost_aud' => floatval($cost),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert when batch size is reached
            if (count($inserts) >= $batchSize) {
                $this->insertChunk($inserts, $rowCount);
                $inserts = [];
            }
        }
        fclose($handle);

        // Insert remaining rows
        if (!empty($inserts)) {
            $this->insertChunk($inserts, $rowCount);
        }

        if ($rowCount === 0) {
            $this->error("No data processed!");
            Log::error("Import failed: No valid data parsed from {$file}");
            return 1;
        }

        $this->info("Imported {$courier} shipping costs from {$file} ({$rowCount} rows processed, " . ($rowCount * 100) . " inserts)");
        Log::info("Imported {$courier} shipping costs: {$rowCount} rows processed");
        return 0;
    }

    private function insertChunk(array $inserts, int $rowCount)
    {
        try {
            DB::table('shipping_costs')->insert($inserts);
            $this->info("Inserted batch for row {$rowCount} (" . count($inserts) . " rows)");
        } catch (\Exception $e) {
            $this->error("Failed to insert batch for row {$rowCount}: " . $e->getMessage());
            Log::error("Insert failed for row {$rowCount}: " . $e->getMessage());
            throw $e;
        }
    }
}