<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_checkout_page_returns_successful_response(): void
    {
        $response = $this->get('/payments');

        $response->assertStatus(200);
    }
}