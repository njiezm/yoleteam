<?php

namespace Tests;

use App\Models\Association;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** Signs in an admin / bureau user of a fresh association. */
    protected function signInAdmin(?Association $association = null): User
    {
        $user = User::factory()->admin()->for($association ?? Association::factory())->create();
        $this->actingAs($user);

        return $user;
    }

    /** Signs in a patron / entraîneur of a fresh association. */
    protected function signInPatron(?Association $association = null): User
    {
        $user = User::factory()->for($association ?? Association::factory())->create();
        $this->actingAs($user);

        return $user;
    }
}
