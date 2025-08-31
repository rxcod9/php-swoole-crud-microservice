<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ItemService;

final class ItemController extends Controller
{
    public function __construct(private ItemService $svc)
    {
        //
    }
    public function create(): array
    {
        $d = json_decode($this->request->rawContent() ?: '[]', true);
        return $this->json($this->svc->create($d), 201);
    }
    public function index(): array
    {
        return $this->json($this->svc->list());
    }
    public function show(array $p): array
    {
        $x = $this->svc->get((int)$p['id']);
        return $x ? $this->json($x) : $this->json(['error' => 'Not Found'], 404);
    }
    public function update(array $p): array
    {
        $d = json_decode($this->request->rawContent() ?: '[]', true);
        return $this->json($this->svc->update((int)$p['id'], $d));
    }
    public function destroy(array $p): array
    {
        return $this->json(['deleted' => $this->svc->delete((int)$p['id'])]);
    }
}
