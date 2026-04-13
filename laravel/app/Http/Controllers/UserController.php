<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserGrpcService;
use App\Support\Grpc\GrpcHttpStatusMapper;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserGrpcService $userGrpcService,
    ) {
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->userGrpcService->getUser($id);

        if (($result['ok'] ?? false) === false) {
            return response()->json(
                ['error' => $result['error'] ?? 'Unknown error'],
                GrpcHttpStatusMapper::map($result['code'] ?? null)
            );
        }

        return (new UserResource($result['data']))
            ->response()
            ->setStatusCode(200);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $result = $this->userGrpcService->createUser(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
        );

        if (($result['ok'] ?? false) === false) {
            return response()->json(
                ['error' => $result['error'] ?? 'Unknown error'],
                GrpcHttpStatusMapper::map($result['code'] ?? null)
            );
        }

        return (new UserResource($result['data']))
            ->response()
            ->setStatusCode(201);
    }
}
