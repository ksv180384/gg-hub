<?php

use Illuminate\Routing\Route;

it('keeps every API endpoint mapped to a callable action without touching the database', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1'));

    expect($routes)->not->toBeEmpty();

    $signatures = [];
    $violations = [];

    foreach ($routes as $route) {
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $signature = $method.' '.$route->uri();

            if (isset($signatures[$signature])) {
                $violations[] = "Duplicate endpoint: {$signature}";
            }

            $signatures[$signature] = true;
        }

        $action = $route->getActionName();

        if ($action === 'Closure') {
            continue;
        }

        [$controller, $method] = str_contains($action, '@')
            ? explode('@', $action, 2)
            : [$action, '__invoke'];

        if (! class_exists($controller)) {
            $violations[] = "Missing controller for {$route->uri()}: {$controller}";

            continue;
        }

        if (! method_exists($controller, $method)) {
            $violations[] = "Missing action for {$route->uri()}: {$action}";

            continue;
        }

        if (! (new ReflectionMethod($controller, $method))->isPublic()) {
            $violations[] = "Non-public action for {$route->uri()}: {$action}";
        }
    }

    expect($violations)->toBe([]);
});
