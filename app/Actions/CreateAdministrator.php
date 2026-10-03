<?php

namespace App\Actions;

use App\Auth\MagicLinkBroker;
use App\Models\Administrator;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates an administrator account and emails it a magic link, so the first
 * access goes through the mandatory "set your password" screen.
 */
class CreateAdministrator
{
    public function __construct(private readonly MagicLinkBroker $magicLinks) {}

    /**
     * @throws ValidationException when the email was taken after validation,
     *                             e.g. by a double submit
     */
    public function handle(string $name, string $email): Administrator
    {
        try {
            return $this->create($name, $email);
        } catch (UniqueConstraintViolationException $exception) {
            Validator::make(['email' => $email], ['email' => 'unique:users,email'])->validate();

            throw $exception;
        }
    }

    private function create(string $name, string $email): Administrator
    {
        return DB::transaction(function () use ($name, $email) {
            $user = User::create(['name' => $name, 'email' => $email]);
            $administrator = $user->administrator()->create();

            $this->magicLinks->send($user);

            return $administrator;
        });
    }
}
