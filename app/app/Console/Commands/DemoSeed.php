<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Local-only fictional demo data. Refuses to run outside local/testing.
 */
#[Signature('app:demo-seed {--fresh : Drop all tables and migrate first (destroys local data)} {--password= : Shared password for demo staff (otherwise generated)}')]
#[Description('Seed deterministic, clearly fictional demo scenarios (local/testing only)')]
class DemoSeed extends Command
{
    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('app:demo-seed зөвхөн local/testing орчинд ажиллана.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('Локал өгөгдлийн санг бүрэн устгаад шинээр үүсгэх үү?', true)) {
                return self::FAILURE;
            }

            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->line(Artisan::output());
        }

        if (Customer::query()->where('is_demo', true)->exists()) {
            $this->warn('DEMO өгөгдөл аль хэдийн байна. Шинээр хийх бол --fresh ашиглана.');

            return self::FAILURE;
        }

        $seeder = app(DemoSeeder::class);
        $seeder->staffPassword = (string) ($this->option('password') ?? '');
        $seeder->setContainer(app())->setCommand($this)->__invoke();

        $this->newLine();
        $this->info('DEMO өгөгдөл үүслээ. Бүх утас 0000xxxx (хүргэгдэхгүй), SMS зөвхөн log.');
        $rows = [
            ['Зөвлөх', 'advisor@demo.local'],
            ['Механик 1', 'mechanic1@demo.local'],
            ['Механик 2', 'mechanic2@demo.local'],
            ['Нууц үг (бүх DEMO ажилтан)', $seeder->staffPassword],
            ['Үйлчлүүлэгч портал', '/portal/nevtreh — утас 0000 0001 (Болд), 0000 0004 (Энхээ: зөвшөөрөл хүлээж буй)'],
            ['Эзэн', 'php artisan app:owner-create'],
        ];
        $this->table(['Хэн', 'Нэвтрэх'], $rows);

        $file = storage_path('app/demo-credentials.txt');
        file_put_contents($file, "LOCAL DEMO ONLY — fictional accounts. Never use in production.\n".collect($rows)->map(fn (array $row): string => $row[0].': '.$row[1])->join("\n")."\n");
        $this->line('Энэ мэдээллийг мөн хадгаллаа (git-д орохгүй): '.$file);

        return self::SUCCESS;
    }
}
