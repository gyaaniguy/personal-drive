<?php

namespace App\Http\Controllers\ShareControllers;

use App\Http\Controllers\Controller;
use App\Models\LocalFile;
use App\Models\Share;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShareDirectDownloadController extends Controller
{
    public function index(Request $request, string $slug): BinaryFileResponse
    {
        $share = Share::whereBySlug($slug)->where('direct', true)->first();
        if (!$share || !$share->enabled || $this->isExpired($share)) {
            throw new NotFoundHttpException();
        }

        if ($share->password && !Hash::check((string) $request->getPassword(), $share->password)) {
            // Username is ignored; only the password is checked
            throw new HttpException(401, 'Password required', null, [
                'WWW-Authenticate' => 'Basic realm="Download", charset="UTF-8"',
            ]);
        }

        $file = $share->localFiles()->first();
        if (!$file instanceof LocalFile || $file->is_dir || !is_file($file->getPrivatePathNameForFile())) {
            throw new NotFoundHttpException();
        }

        return response()->download($file->getPrivatePathNameForFile(), $file->filename);
    }

    private function isExpired(Share $share): bool
    {
        return $share->expiry && $share->created_at->addDays($share->expiry)->lt(now());
    }
}
