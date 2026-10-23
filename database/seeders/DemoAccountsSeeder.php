<?php

namespace Database\Seeders;

use App\Models\Mapel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (env('DEMO_DATA_ONLY') !== true && env('DEMO_DATA_ONLY') !== 'true') {
            throw new RuntimeException('Demo seeding dibatalkan: set DEMO_DATA_ONLY=true hanya untuk database terisolasi.');
        }

        if (env('ALLOW_DEMO_ACCOUNT_RESET') !== true && env('ALLOW_DEMO_ACCOUNT_RESET') !== 'true') {
            throw new RuntimeException('Demo seeding dibatalkan: set ALLOW_DEMO_ACCOUNT_RESET=true untuk mengizinkan reset akun.');
        }

        $password = env('DEMO_ACCOUNT_PASSWORD');
        $expectedDatabase = env('DEMO_DATABASE_NAME');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Demo seeding dibatalkan: DEMO_ACCOUNT_PASSWORD belum diisi.');
        }

        if (! is_string($expectedDatabase) || $expectedDatabase === '') {
            throw new RuntimeException('Demo seeding dibatalkan: DEMO_DATABASE_NAME belum diisi.');
        }

        $connection = DB::connection();
        $actualDatabase = $connection->getDatabaseName();

        if (! hash_equals($expectedDatabase, $actualDatabase)) {
            throw new RuntimeException('Demo seeding dibatalkan: nama database aktif tidak cocok dengan DEMO_DATABASE_NAME.');
        }

        DB::transaction(function () use ($password) {
            $mapel = Mapel::firstOrCreate(
                ['kode' => 'MTK-B'],
                ['nama_mapel' => 'Matematika', 'paket' => 'B'],
            );

            $accounts = [
                [
                    'nama' => 'Admin Demo',
                    'email' => 'admin@lms-kesetaraan.sch.id',
                    'role' => 'admin',
                    'admin_level' => 'super',
                    'nip' => null,
                    'nis' => null,
                ],
                [
                    'nama' => 'Budi Santoso (Demo)',
                    'email' => 'budi.guru@lms-kesetaraan.sch.id',
                    'role' => 'guru',
                    'admin_level' => 'staff',
                    'nip' => 'G001',
                    'nis' => null,
                ],
                [
                    'nama' => 'Rika Pramestia (Demo)',
                    'email' => 'rika.murid@lms-kesetaraan.sch.id',
                    'role' => 'murid',
                    'admin_level' => 'staff',
                    'nip' => null,
                    'nis' => 'B2024001',
                ],
            ];

            foreach ($accounts as $attributes) {
                $this->assertIdentifierAvailable($attributes['email'], 'nip', $attributes['nip']);
                $this->assertIdentifierAvailable($attributes['email'], 'nis', $attributes['nis']);

                $user = User::withTrashed()->firstOrNew(['email' => $attributes['email']]);
                $user->forceFill([
                    ...$attributes,
                    'password' => Hash::make($password),
                    'status' => 'aktif',
                ]);
                $user->deleted_at = null;
                $user->save();
                $user->tokens()->delete();

                if ($attributes['role'] === 'guru') {
                    $user->guruProfile()->updateOrCreate([], ['mapel_id' => $mapel->id]);
                }

                if ($attributes['role'] === 'murid') {
                    $user->muridProfile()->updateOrCreate([], [
                        'paket' => 'B',
                        'status_kelulusan' => 'pending',
                    ]);
                }
            }
        });

        $this->command?->info('Tiga akun demo dibuat atau diperbarui. Tidak ada password yang ditampilkan.');
    }

    private function assertIdentifierAvailable(string $email, string $column, ?string $identifier): void
    {
        if ($identifier === null) {
            return;
        }

        $inUse = User::withTrashed()
            ->where($column, $identifier)
            ->where('email', '!=', $email)
            ->exists();

        if ($inUse) {
            throw new RuntimeException("Demo seeding dibatalkan: {$column} target sudah digunakan akun lain.");
        }
    }
}
