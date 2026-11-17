<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsageReportRequest;
use App\Services\Pixelforge\UsageReconciler;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function __invoke(UsageReportRequest $request, UsageReconciler $reconciler): Response
    {
        $report = $reconciler->reconcile(
            startDate: $request->startDate(),
            endDate: $request->endDate(),
        );

        return Inertia::render('usage/Index', [
            'report' => $report,
            'filters' => [
                'start_date' => $report['start_date'],
                'end_date' => $report['end_date'],
            ],
        ]);
    }
}
