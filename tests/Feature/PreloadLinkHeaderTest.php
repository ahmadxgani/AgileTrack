<?php

namespace Tests\Feature;

use Tests\TestCase;

class PreloadLinkHeaderTest extends TestCase
{
    /**
     * nginx rejects a FastCGI response whose header block exceeds its 4 KB
     * buffer with a 502. The preload Link header must stay well inside
     * that after the session cookies are added.
     */
    public function test_preload_link_header_is_bounded(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();

        $link = $response->headers->get('Link');

        $this->assertNotNull($link, 'The preload Link header should still be sent.');
        $this->assertLessThanOrEqual(12, count(explode(', ', $link)));
        $this->assertLessThan(2048, strlen($link));
    }
}
