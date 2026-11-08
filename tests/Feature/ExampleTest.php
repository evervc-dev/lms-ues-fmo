<?php

it('redirects guests from the home page to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});
