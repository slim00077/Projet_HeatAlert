<?php

it('serves the public about and emergency pages without dead links', function () {
    $this->get(route('pages.about'))->assertOk()->assertSee('Stay safe during heat waves');
    $this->get(route('pages.emergency'))->assertOk()->assertSee('190');

    $this->get('/')->assertSee(route('pages.about'), false);
});
