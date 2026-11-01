<?php

test('the home page redirects to the daily board', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
