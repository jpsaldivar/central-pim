<?php

namespace App\Controllers;

class Jumpseller extends BaseController
{
    public function index(): string
    {
        return view('jumpseller/index', [
            'title' => 'Jumpseller',
        ]);
    }
}
