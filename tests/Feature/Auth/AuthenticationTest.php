<?php


test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(302);
});
