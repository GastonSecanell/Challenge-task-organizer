<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {}

    public function index()
    {
        $roles = $this->roleService->list();

        return RoleResource::collection($roles);
    }
}
