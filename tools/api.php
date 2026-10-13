<?php
// Describes the public API (core/api.php) by reflection, and checks a frozen copy against the current code.
// Used by `php tools/pagebrick.php api-snapshot` and by tests/CompatibilityTest.php. Not shipped in releases.

/** The public API as it is now: signatures, constants, hooks and the shape of the standard content. */
function pb_api_describe(): array
{
    $api = require PB_ROOT . '/core/api.php';
    $describe = fn(ReflectionFunctionAbstract $f) => [
        'params' => array_map(fn(ReflectionParameter $p) => [
            'name' => $p->getName(),
            'type' => (string) $p->getType(),
            'optional' => $p->isOptional(),
        ], $f->getParameters()),
        'returns' => (string) $f->getReturnType(),
    ];
    $description = ['api' => PB_API_VERSION, 'functions' => [], 'classes' => [], 'constants' => $api['constants'], 'hooks' => $api['hooks'],
        'standard' => pb_api_standard_shape()];
    foreach ($api['functions'] as $name) {
        $description['functions'][$name] = $describe(new ReflectionFunction($name));
    }
    foreach ($api['classes'] as $class) {
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() !== '__construct') {
                $description['classes'][$class][$method->getName()] = $describe($method);
            }
        }
    }
    return $description;
}

/** Field paths and types of the standard content, e.g. "templates.home.hero.title" => "text". */
function pb_api_standard_shape(): array
{
    $shape = [];
    $walk = function (array $fields, string $prefix) use (&$walk, &$shape) {
        foreach ($fields as $name => $def) {
            $shape["$prefix$name"] = $def['type'];
            if (isset($def['fields'])) {
                $walk($def['fields'], "$prefix$name.");
            }
        }
    };
    foreach (pb_standard_templates() as $template => $def) {
        $walk($def['fields'], "templates.$template.");
    }
    $walk(pb_standard_settings(), 'settings.');
    foreach (array_keys(pb_standard_menus()) as $menu) {
        $shape["menus.$menu"] = 'menu';
    }
    ksort($shape);
    return $shape;
}

/** True when a value of type $old is always accepted by type $new ("string" -> "?string" is fine, the reverse isn't). */
function pb_api_type_accepts(string $new, string $old): bool
{
    $parts = fn(string $type) => array_map(fn($t) => ltrim($t, '?'), explode('|', str_starts_with($type, '?') ? substr($type, 1) . '|null' : $type));
    return $new === '' || $new === 'mixed' || $old === $new || !array_diff($parts($old), $parts($new));
}

/** Every way the current code breaks the promise frozen in $frozen. Empty means existing themes and plugins keep working. */
function pb_api_breaks(array $frozen, array $now): array
{
    $breaks = [];
    $compare = function (string $what, array $old, ?array $new) use (&$breaks) {
        if ($new === null) {
            $breaks[] = "$what was removed";
            return;
        }
        if ($old['returns'] !== '' && !pb_api_type_accepts($old['returns'], $new['returns'])) {
            $breaks[] = "$what: return type changed from '{$old['returns']}' to '{$new['returns']}'";
        }
        foreach ($old['params'] as $i => $param) {
            $current = $new['params'][$i] ?? null;
            if ($current === null) {
                $breaks[] = "$what: parameter \${$param['name']} was removed";
            } elseif ($current['name'] !== $param['name']) {
                $breaks[] = "$what: parameter \${$param['name']} was renamed or moved (now \${$current['name']})";
            } elseif (!pb_api_type_accepts($current['type'], $param['type'])) {
                $breaks[] = "$what: parameter \${$param['name']} no longer accepts '{$param['type']}' (now '{$current['type']}')";
            } elseif ($param['optional'] && !$current['optional']) {
                $breaks[] = "$what: parameter \${$param['name']} became required";
            }
        }
        foreach (array_slice($new['params'], count($old['params'])) as $added) {
            if (!$added['optional']) {
                $breaks[] = "$what: the new parameter \${$added['name']} must be optional";
            }
        }
    };
    foreach ($frozen['functions'] as $name => $old) {
        $compare("function $name()", $old, $now['functions'][$name] ?? null);
    }
    foreach ($frozen['classes'] as $class => $methods) {
        foreach ($methods as $method => $old) {
            $compare("$class::$method()", $old, $now['classes'][$class][$method] ?? null);
        }
    }
    foreach ($frozen['constants'] as $constant) {
        if (!in_array($constant, $now['constants'], true) || !defined($constant)) {
            $breaks[] = "constant $constant was removed";
        }
    }
    foreach (array_diff($frozen['hooks'], $now['hooks']) as $hook) {
        $breaks[] = "hook '$hook' was removed";
    }
    foreach ($frozen['standard'] as $path => $type) {
        if (!isset($now['standard'][$path])) {
            $breaks[] = "standard field $path was removed";
        } elseif ($now['standard'][$path] !== $type) {
            $breaks[] = "standard field $path changed from $type to {$now['standard'][$path]}";
        }
    }
    if ($now['api'] !== $frozen['api']) {
        $breaks[] = "PB_API_VERSION changed from {$frozen['api']} to {$now['api']}: that is a new promise, freeze it in a new file";
    }
    return $breaks;
}
