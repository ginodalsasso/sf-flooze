<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects to a target supplied by the request (redirect_to, Referer) without
 * ever leaving the site. Any target that is not local falls back to a route.
 */
trait SafeRedirectTrait
{
    protected function safeRedirect(Request $request, ?string $target, string $fallbackRoute): Response
    {
        $path = $this->toLocalPath($request, $target);

        return $path !== null
            ? $this->redirect($path)
            : $this->redirectToRoute($fallbackRoute);
    }

    /** Returns $target as a local path, or null when it points outside this host. */
    private function toLocalPath(Request $request, ?string $target): ?string
    {
        if ($target === null || $target === '') {
            return null;
        }

        // An absolute URL (a Referer header) survives only when it targets this very host.
        $base = $request->getSchemeAndHttpHost();
        if (str_starts_with($target, $base . '/')) {
            $target = substr($target, strlen($base));
        }

        // "//host" and "/\host" are protocol-relative URLs: a browser resolves them to another origin.
        if (!str_starts_with($target, '/')
            || str_starts_with($target, '//')
            || str_starts_with($target, '/\\')
        ) {
            return null;
        }

        return $target;
    }
}
