<?php

namespace Guixa\Membership\Application;

final readonly class HouseholdDetail
{
    /**
     * @param  list<MemberView>  $members
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public array $members,
    ) {}
}
