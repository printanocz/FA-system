<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Vytvoří uživatele aplikace (registrace je vypnutá)';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->ask('E-mail')));
        $name = trim((string) $this->ask('Jméno'));
        $password = (string) $this->secret('Heslo (min. '.PasswordPolicy::MIN_LENGTH.' znaků)');
        $confirmation = (string) $this->secret('Heslo znovu');

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => PasswordPolicy::rules(confirmed: true),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Uživatel {$email} byl vytvořen. Při prvním přihlášení musí zapnout dvoufázové ověření.");

        return self::SUCCESS;
    }
}
