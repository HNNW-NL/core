<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

final class AuthProfilerMatcher implements RequestMatcherInterface
{
    public function matches(Request $request): bool
    {
        return !preg_match('~^/auth(?:/|$)~', $request->getPathInfo());
    }
}
