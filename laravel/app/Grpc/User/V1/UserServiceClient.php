<?php
// GENERATED CODE -- DO NOT EDIT!

namespace User\V1;

/**
 */
class UserServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \User\V1\GetUserRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetUser(\User\V1\GetUserRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/user.v1.UserService/GetUser',
        $argument,
        ['\User\V1\GetUserResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \User\V1\CreateUserRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateUser(\User\V1\CreateUserRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/user.v1.UserService/CreateUser',
        $argument,
        ['\User\V1\CreateUserResponse', 'decode'],
        $metadata, $options);
    }

}
