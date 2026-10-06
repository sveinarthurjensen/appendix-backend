<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * php artisan app:make-admin <e-post> [--name=] [--password=] [--app=appendix_properties]
 * Oppretter eller oppdaterer en admin-bruker. Uten --password genereres et og skrives ut.
 */
class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email} {--name=} {--password=} {--app=appendix_properties}';
    protected $description = 'Opprett/oppdater admin-bruker';

    public function handle(): int
    {
        $password = $this->option('password') ?: Str::password(16, symbols: false);
        $user = User::firstOrNew(['app_id' => $this->option('app'), 'email' => $this->argument('email')]);
        if (!$user->exists) {
            $user->id = strtolower((string) Str::ulid());
        }
        $user->fill([
            'full_name' => $this->option('name') ?: $user->full_name ?: $this->argument('email'),
            'role' => 'admin',
            'admin_approved' => true,
            'password' => $password,
        ])->save();

        $this->info(($user->wasRecentlyCreated ? 'Opprettet' : 'Oppdaterte') . " admin {$user->email}");
        $this->line("PASSORD: $password");
        return self::SUCCESS;
    }
}
