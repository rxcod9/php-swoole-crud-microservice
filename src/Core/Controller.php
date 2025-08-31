<?php

namespace App\Core;

abstract class Controller
{
    protected $request;
    // protected $response;
    public function setRequest($req)
    {
        $this->request = $req;
    }
    // public function setResponse($res)
    // {
    //     $this->response = $res;
    // }
    protected function json($data, int $status = 200): array
    {
        return ['__status' => $status, '__json' => $data];
    }
}
