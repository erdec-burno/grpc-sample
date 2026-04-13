<?php

namespace App\Support\Grpc;

use Grpc\ChannelCredentials;
use User\V1\UserServiceClient;

class GrpcClientFactory
{
    public static function userService(): UserServiceClient
    {
        $host = config('services.grpc.user_service_host');

        return new UserServiceClient($host, [
            'credentials' => ChannelCredentials::createInsecure(),
            // channel args if needed
        ]);
    }
}
