<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SystemController extends Controller
{
    public function index()
    {
        $information = [
            'Application' => config('app.name'),
            'Environment' => app()->environment(),
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Timezone' => config('app.timezone'),
            'Locale' => app()->getLocale(),
            'Debug mode' => config('app.debug') ? 'Enabled' : 'Disabled',
        ];

        return view('admin.system.index', compact('information'));
    }
}
