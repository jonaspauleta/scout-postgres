<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\EngineManager;

#[Signature('bench:scout {--seed=0 : Seed N books before benchmarking (0 = skip)} {--runs=30 : Measured runs per query} {--warmup=3 : Warmup runs per query} {--limit=20 : take(N) per search call}')]
#[Description('Benchmark scout-postgres vs Scout database driver on the books table')]
final class BenchScout extends Command
{
    /** @var array<int, array{label: string, query: string}> */
    private const QUERIES = [
        ['label' => 'common_token',     'query' => 'world'],
        ['label' => 'two_words',        'query' => 'modern history'],
        ['label' => 'rare_phrase',      'query' => 'philosophical exposition'],
        ['label' => 'prefix_partial',   'query' => 'phil'],
        ['label' => 'typo',             'query' => 'philosphy'],
        ['label' => 'no_match',         'query' => 'qwxzqwxzqwxz'],
        ['label' => 'long_query',       'query' => 'a comprehensive history of modern philosophical thought'],
    ];

    public function handle(): int
    {
        $seed = (int) $this->option('seed');
        if ($seed > 0) {
            $this->seedBooks($seed);
        }

        $rowCount = (int) Book::query()->count();
        $this->info("Rows in books table: {$rowCount}");

        if ($rowCount === 0) {
            $this->error('No rows. Pass --seed=N first.');

            return self::FAILURE;
        }

        $runs = (int) $this->option('runs');
        $warmup = (int) $this->option('warmup');
        $limit = (int) $this->option('limit');

        $this->line('');
        $this->info("Bench: rows={$rowCount}, warmup={$warmup}, runs={$runs}, limit={$limit}");
        $this->line('');

        $results = [];

        foreach (['pgsql', 'database'] as $driver) {
            $this->line("--- Driver: {$driver} ---");
            config(['scout.driver' => $driver]);

            // Force fresh engine resolution for each driver.
            app()->forgetInstance(EngineManager::class);

            foreach (self::QUERIES as $q) {
                $samples = $this->benchmark($q['query'], $limit, $warmup, $runs);
                $hits = $samples['last_hits'];

                $results[$q['label']][$driver] = [
                    'p50' => $samples['p50'],
                    'p95' => $samples['p95'],
                    'mean' => $samples['mean'],
                    'hits' => $hits,
                ];

                $this->line(sprintf(
                    '  %-16s  p50=%6.2fms  p95=%6.2fms  mean=%6.2fms  hits=%d',
                    $q['label'],
                    $samples['p50'],
                    $samples['p95'],
                    $samples['mean'],
                    $hits,
                ));
            }
            $this->line('');
        }

        $this->renderTable($results);

        return self::SUCCESS;
    }

    private function seedBooks(int $count): void
    {
        $this->info("Seeding {$count} books...");
        Book::query()->truncate();

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $chunk = 500;
        $remaining = $count;
        while ($remaining > 0) {
            $size = min($chunk, $remaining);
            DB::transaction(function () use ($size): void {
                Book::factory()->count($size)->create();
            });
            $bar->advance($size);
            $remaining -= $size;
        }

        $bar->finish();
        $this->line('');

        // Sprinkle deterministic content so we have known matches.
        DB::transaction(function (): void {
            for ($i = 0; $i < 50; $i++) {
                Book::factory()->create([
                    'title' => 'A Comprehensive History of Modern Philosophical Thought',
                    'author' => 'Immanuel Kant',
                    'summary' => 'This is a philosophical exposition of the modern era covering ethics, metaphysics and epistemology in fine detail.',
                ]);
            }
            for ($i = 0; $i < 100; $i++) {
                Book::factory()->create([
                    'title' => 'Modern World History Encyclopedia Volume '.($i + 1),
                    'author' => 'Various Authors',
                    'summary' => 'A reference work on modern world history. Topics include geopolitics, economics, and culture.',
                ]);
            }
        });

        $this->info('Seed complete. Running ANALYZE to refresh planner stats.');
        DB::statement('ANALYZE books');
    }

    /**
     * @return array{p50: float, p95: float, mean: float, last_hits: int}
     */
    private function benchmark(string $query, int $limit, int $warmup, int $runs): array
    {
        // Warmup
        for ($i = 0; $i < $warmup; $i++) {
            Book::search($query)->take($limit)->get();
        }

        $samples = [];
        $lastHits = 0;
        for ($i = 0; $i < $runs; $i++) {
            $start = hrtime(true);
            $hits = Book::search($query)->take($limit)->get();
            $elapsed = (hrtime(true) - $start) / 1_000_000.0; // ms
            $samples[] = $elapsed;
            $lastHits = $hits->count();
        }

        sort($samples);
        $p50 = $samples[(int) floor(count($samples) * 0.50)];
        $p95 = $samples[(int) floor(count($samples) * 0.95)];
        $mean = array_sum($samples) / count($samples);

        return [
            'p50' => $p50,
            'p95' => $p95,
            'mean' => $mean,
            'last_hits' => $lastHits,
        ];
    }

    /**
     * @param  array<string, array<string, array{p50: float, p95: float, mean: float, hits: int}>>  $results
     */
    private function renderTable(array $results): void
    {
        $rows = [];
        foreach ($results as $label => $drivers) {
            $pgsql = $drivers['pgsql'] ?? null;
            $database = $drivers['database'] ?? null;
            if ($pgsql === null || $database === null) {
                continue;
            }

            $speedup = $pgsql['p50'] > 0
                ? sprintf('%.1fx', $database['p50'] / $pgsql['p50'])
                : 'n/a';

            $rows[] = [
                $label,
                sprintf('%.2f', $pgsql['p50']),
                sprintf('%.2f', $pgsql['p95']),
                sprintf('%.2f', $database['p50']),
                sprintf('%.2f', $database['p95']),
                $speedup,
                $pgsql['hits'].' / '.$database['hits'],
            ];
        }

        $this->line('=== Summary (latency in ms, lower is better) ===');
        $this->table(
            ['query', 'pgsql p50', 'pgsql p95', 'database p50', 'database p95', 'speedup p50', 'hits pg/db'],
            $rows,
        );
    }
}
