<?php

namespace App\Http\Controllers;

use App\Models\AktivitasLog;

class AktivitasLogController extends Controller
{
    public function index()
    {
        $logs = AktivitasLog::with('user')
            ->latest()
            ->paginate(15);

        return view('dinas.log', compact('logs'));
    }
}