<?php

namespace Tests\Feature;

use Tests\TestCase;

class SiteWebmanifestTest extends TestCase
{
    public function test_site_webmanifest_is_served_as_json_with_the_manifest_mime_type(): void
    {
        $response = $this->get(route('site.webmanifest'));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/manifest+json',
            (string) $response->headers->get('Content-Type'),
        );

        $manifest = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('icons', $manifest);
    }
}
