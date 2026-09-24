<?php

test('guests visiting the home page are sent to login', function () {
    $this->get('/')->assertRedirect('/login');
});

test('the login page loads', function () {
    $this->get('/login')->assertSuccessful();
});
