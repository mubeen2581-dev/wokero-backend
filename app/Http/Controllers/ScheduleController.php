<?php

namespace App\Http\Controllers;

use App\Models\ScheduleEvent;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function events(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = ScheduleEvent::where('company_id', $companyId)
            ->with('job', 'technician');

        if ($request->has('start') && $request->has('end')) {
            $query->whereBetween('start', [
                $request->input('start'),
                $request->input('end'),
            ]);
        }

        if ($request->has('technician_id')) {
            $query->where('technician_id', $request->input('technician_id'));
        }

        $events = $query->get();

        return $this->success($events->toArray());
    }

    public function availability(Request $request)
    {
        // TODO: Implement technician availability check
        return $this->error('Not implemented', null, 501);
    }

    public function conflicts(Request $request)
    {
        // TODO: Implement schedule conflict detection
        return $this->error('Not implemented', null, 501);
    }
}

