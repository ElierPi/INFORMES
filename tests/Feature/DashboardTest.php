<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertSuccessful()
        ->assertSee(route('informes.resolucion-0256'))
        ->assertSee(route('informes.demanda-inducida'));
});
