<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Renderer;

final class TemplateRenderer
{
    public function render(string $template, array $vars): string
    {
        $vars = $this->normalize($vars);

        return $this->renderSections($this->renderVars($template, $vars), $vars);
    }

    private function renderSections(string $template, array $vars): string
    {
        $pattern = '/{{\s*#\s*([A-Za-z0-9_.-]+)\s*}}(.*?){{\s*\/\s*\1\s*}}/s';

        while (preg_match($pattern, $template)) {
            $template = preg_replace_callback($pattern, function (array $m) use ($vars): string {
                $key = (string) $m[1];
                $inner = (string) $m[2];

                $value = $this->get($vars, $key);

                if ($value === null || $value === false || $value === '' || $value === 0) {
                    return '';
                }

                if (is_array($value)) {
                    if ($this->isAssoc($value)) {
                        return $this->renderSections($this->renderVars($inner, $this->merge($vars, $this->normalize($value))), $this->merge($vars, $this->normalize($value)));
                    }

                    $out = '';
                    foreach ($value as $row) {
                        $rowVars = $this->normalize(is_array($row) ? $row : ['.' => $row]);
                        $ctx = $this->merge($vars, $rowVars);
                        $out .= $this->renderSections($this->renderVars($inner, $ctx), $ctx);
                    }

                    return $out;
                }

                if (is_object($value)) {
                    $ctx = $this->merge($vars, $this->normalize(get_object_vars($value)));
                    return $this->renderSections($this->renderVars($inner, $ctx), $ctx);
                }

                return $this->renderSections($this->renderVars($inner, $vars), $vars);
            }, $template);
        }

        return $template;
    }

    private function renderVars(string $template, array $vars): string
    {
        $pattern = '/{{\s*([A-Za-z0-9_.-]+)\s*}}/';

        return preg_replace_callback($pattern, function (array $m) use ($vars): string {
            $key = (string) $m[1];
            $value = $this->get($vars, $key);

            if ($value === null) {
                return '';
            }

            if (is_bool($value)) {
                return $value ? '1' : '';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            if (is_array($value)) {
                $flat = [];
                foreach ($value as $v) {
                    if (is_scalar($v) || $v === null) {
                        $flat[] = (string) ($v ?? '');
                    }
                }
                if ($flat !== []) {
                    return implode("\n", $flat);
                }

                return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
            }

            return '';
        }, $template) ?? $template;
    }

    private function get(array $vars, string $path): mixed
    {
        if ($path === '.') {
            return $vars['.'] ?? null;
        }

        $cur = $vars;
        foreach (explode('.', $path) as $seg) {
            if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                return null;
            }
            $cur = $cur[$seg];
        }

        return $cur;
    }

    private function normalize(array $vars): array
    {
        $out = [];
        foreach ($vars as $k => $v) {
            $key = is_int($k) ? (string) $k : (string) $k;

            if ($v instanceof \ArrayObject) {
                $out[$key] = $this->normalize($v->getArrayCopy());
                continue;
            }

            if (is_object($v) && method_exists($v, 'getArrayCopy')) {
                $arr = $v->getArrayCopy();
                $out[$key] = is_array($arr) ? $this->normalize($arr) : $arr;
                continue;
            }

            if (is_array($v)) {
                $out[$key] = $this->normalizeArrayDeep($v);
                continue;
            }

            $out[$key] = $v;
        }

        return $out;
    }

    private function normalizeArrayDeep(array $value): array
    {
        $out = [];
        foreach ($value as $k => $v) {
            $key = is_int($k) ? $k : (string) $k;

            if ($v instanceof \ArrayObject) {
                $out[$key] = $this->normalize($v->getArrayCopy());
                continue;
            }

            if (is_array($v)) {
                $out[$key] = $this->normalizeArrayDeep($v);
                continue;
            }

            if (is_object($v)) {
                $out[$key] = get_object_vars($v);
                continue;
            }

            $out[$key] = $v;
        }

        return $out;
    }

    private function merge(array $base, array $extra): array
    {
        foreach ($extra as $k => $v) {
            $base[$k] = $v;
        }

        return $base;
    }

    private function isAssoc(array $arr): bool
    {
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return true;
            }
            $i++;
        }

        return false;
    }
}
