<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase; // the home page shows the institute name, which lives in the database

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200)->assertSee(route('login'), false);
    }
}
