<?php

it('serves the public home page', function () {
    $this->get('/')->assertOk();
});
