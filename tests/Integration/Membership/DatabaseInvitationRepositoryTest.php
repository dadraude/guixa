<?php

namespace Tests\Integration\Membership;

use App\Models\User;
use Guixa\Membership\Domain\InvitationRepository;
use Guixa\Membership\Infrastructure\Persistence\DatabaseInvitationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Contracts\InvitationRepositoryContract;
use Tests\TestCase;

final class DatabaseInvitationRepositoryTest extends TestCase
{
    use InvitationRepositoryContract;
    use RefreshDatabase;

    protected function repository(): InvitationRepository
    {
        return new DatabaseInvitationRepository;
    }

    protected function accountId(): string
    {
        return User::factory()->notActivated()->create()->id;
    }
}
