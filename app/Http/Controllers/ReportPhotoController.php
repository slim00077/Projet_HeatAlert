<?php

namespace App\Http\Controllers;

use App\Models\ResidentReport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report photos live on the private disk and are only streamed to the author, the residence manager and admins.
 */
class ReportPhotoController extends Controller
{
    public function __invoke(ResidentReport $residentReport): StreamedResponse
    {
        Gate::authorize('viewInsights', $residentReport);

        abort_unless($residentReport->hasPhoto() && Storage::disk('local')->exists($residentReport->photo_path), 404);

        return Storage::disk('local')->response($residentReport->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
