<?php

namespace App\Console\Commands;

use DomainException;
use Guixa\Membership\Application\CreateFirstManager;
use Illuminate\Console\Command;

class CreateManager extends Command
{
    protected $signature = 'guixa:create-manager {name : The manager\'s name} {email : Where the invitation is sent}';

    protected $description = 'Create the first manager and send the invitation (only works while no manager exists)';

    public function handle(CreateFirstManager $createFirstManager): int
    {
        $name = $this->textArgument('name');
        $email = $this->textArgument('email');

        try {
            $createFirstManager($name, $email);
        } catch (DomainException $e) {
            $this->error(__('errors.'.class_basename($e)));

            return self::FAILURE;
        }

        $this->info(__('Invitation sent to :email.', ['email' => $email]));

        return self::SUCCESS;
    }

    private function textArgument(string $key): string
    {
        $value = $this->argument($key);

        return is_string($value) ? $value : '';
    }
}
