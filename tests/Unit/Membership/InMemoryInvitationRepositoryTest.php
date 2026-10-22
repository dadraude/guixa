<?php

namespace Tests\Unit\Membership;

use Guixa\Membership\Domain\InvitationRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Contracts\InvitationRepositoryContract;
use Tests\Support\InMemory\InMemoryInvitationRepository;

final class InMemoryInvitationRepositoryTest extends TestCase
{
    use InvitationRepositoryContract;

    protected function repository(): InvitationRepository
    {
        return new InMemoryInvitationRepository;
    }

    protected function accountId(): string
    {
        return '00000000-0000-7000-8000-00000000000a';
    }
}
