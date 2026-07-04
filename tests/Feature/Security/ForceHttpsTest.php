<?php

it('redirects plain HTTP requests to HTTPS in production', function () {
    app()->instance('env', 'production');

    $response = $this->get('/login');

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toStartWith('https://');

    app()->instance('env', 'testing');
});

it('does not redirect when the request is already secure', function () {
    app()->instance('env', 'production');

    $response = $this->get('https://localhost/login');

    $response->assertStatus(200);

    app()->instance('env', 'testing');
});

it('does not redirect non-production environments', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

it('leaves the biometric device route reachable over plain HTTP in production', function () {
    config(['services.biometric.device_serial' => 'NFZ8243301103']);
    app()->instance('env', 'production');

    $response = $this->get('/iclock/cdata?SN=NFZ8243301103&options=all');

    $response->assertStatus(200);

    app()->instance('env', 'testing');
});
