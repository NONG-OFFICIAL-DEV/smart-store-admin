<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * Every PK in this app is a UUID string — (int) casts both sides to 0,
 * making this always true and letting any authenticated user subscribe to
 * any other user's private notification channel. Fixed to a string compare
 * (same bug, same fix, already found in photo-studio-saas's scaffolded
 * version of this exact file).
 */
Broadcast::channel('App.Models.User.{id}', function ($user, string $id) {
    return (string) $user->id === $id;
});
