<?php

declare(strict_types=1);

it('redirects home to the admin panel', function (): void {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});
