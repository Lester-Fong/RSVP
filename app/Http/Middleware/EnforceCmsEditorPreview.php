<?php

namespace App\Http\Middleware;

use App\Services\CmsEditorPreview;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCmsEditorPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! CmsEditorPreview::handles($request)) {
            return $next($request);
        }
        CmsEditorPreview::enforce();
        $response = $next($request);
        foreach (CmsEditorPreview::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
