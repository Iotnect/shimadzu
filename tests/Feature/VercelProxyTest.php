<?php

namespace Tests\Feature;

use Tests\TestCase;

class VercelProxyTest extends TestCase
{
    public function test_vercel_generates_https_redirects_behind_its_proxy(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'untrusted.example.test'])
            ->get('http://shimadzu.vercel.app/')
            ->assertRedirect('https://shimadzu.vercel.app/login');
    }

    public function test_local_requests_do_not_trust_forwarded_scheme_headers(): void
    {
        config(['trustedproxy.proxies' => []]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://localhost/')
            ->assertRedirect('http://localhost/login');
    }
}
