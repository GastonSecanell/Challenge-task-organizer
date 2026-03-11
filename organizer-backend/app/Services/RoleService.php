<?php

namespace App\Services;

use App\Repositories\RoleRepository;

class RoleService
{
    public function __construct(
        private RoleRepository $repository
    ) {}

    public function list()
    {
        return $this->repository->getAll();
    }
}
