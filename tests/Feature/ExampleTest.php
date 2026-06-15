<?php

test('the application redirects from root path', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
});
