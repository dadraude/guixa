<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\MagicLinkBroker;
use App\Models\Administrator;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('guixa:create-owner {email : The owner\'s email address} {name : The owner\'s full name}')]
#[Description('Create the owner (an active member and the first administrator) and email them a magic link')]
class CreateOwner extends Command
{
    public function handle(MagicLinkBroker $magicLinks): int
    {
        if (Administrator::where('is_owner', true)->exists()) {
            $this->error(__('An owner already exists.'));

            return self::FAILURE;
        }

        $email = Str::lower((string) $this->argument('email'));
        $name = (string) $this->argument('name');

        $validator = Validator::make(compact('email', 'name'), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $name, $magicLinks) {
            $owner = User::create(['email' => $email, 'name' => $name]);
            // Every user is a member, the owner included; no household until they join one.
            $owner->member()->create(['household_id' => null, 'is_active' => true]);
            $owner->administrator()->create(['is_owner' => true]);

            $magicLinks->send($owner);
        });

        $this->info(__('Owner :email created. A link to log in has been emailed to them.', ['email' => $email]));

        return self::SUCCESS;
    }
}
