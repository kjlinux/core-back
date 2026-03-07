<?php

use Illuminate\Support\Facades\Broadcast;

// Public channels: attendance, feelback, devices
// No authorization needed for public channels

// Private channel for user-specific notifications
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});
