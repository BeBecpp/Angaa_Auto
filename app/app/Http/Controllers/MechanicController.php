<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MechanicController extends Controller
{
    public function index(): View
    {
        return view('mechanic.index');
    }

    public function show(Request $request, WorkOrder $workOrder): View
    {
        $user = $request->user();
        abort_unless($user->isOfficeStaff() || $workOrder->assigned_mechanic_id === $user->id, 404);

        return view('mechanic.show', ['workOrder' => $workOrder]);
    }
}
