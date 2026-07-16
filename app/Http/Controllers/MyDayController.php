<?php

namespace App\Http\Controllers;

use App\Services\MyDayService;
use Illuminate\View\View;

class MyDayController extends Controller
{
    public function __construct(private readonly MyDayService $myDay)
    {
    }

    public function index(): View
    {
        return view('my-day.index', [
            'items' => $this->myDay->worklist(auth()->user()),
        ]);
    }
}
