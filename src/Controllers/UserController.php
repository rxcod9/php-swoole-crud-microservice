<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\UserService;

final class UserController extends Controller
{
    public function __construct(private UserService $svc)
    {
    }
    public function create(): array
    {
        $data = json_decode($this->request->rawContent() ?: '[]', true);
        $user = $this->svc->create($data);
        return $this->json($user, 201);
    }
    public function index(): array
    {
        return $this->json($this->svc->list());
    }
    public function show(array $params): array
    {
        $u = $this->svc->get((int)$params['id']);
        if (!$u) {
            return $this->json(['error' => 'Not Found'], 404);
        }
        return $this->json($u);
    }
    public function update(array $p): array
    {
        $data = json_decode($this->request->rawContent() ?: '[]', true);
        $u = $this->svc->update((int)$p['id'], $data);
        if (!$u) {
            return $this->json(['error' => 'Not Found'], 404);
        }
        return $this->json($u);
    }
    public function destroy(array $p): array
    {
        $ok = $this->svc->delete((int)$p['id']);
        return $this->json(['deleted' => $ok]);
    }
}
