<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class XssSanitizer
{
    private array $skipFields;

    public function __construct()
    {
        $this->skipFields = config('security.xss.skip_fields', []);
    }

    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $sanitized = $this->sanitizeArray($request->all());
            $request->merge($sanitized);
        }

        return $next($request);
    }

    private function sanitizeArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $this->skipFields)) {
                $result[$key] = $value;
                continue;
            }

            $result[$key] = $this->sanitizeValue($value);
        }

        return $result;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->sanitizeValue($v), $value);
        }

        return $value;
    }

    private function sanitizeString(string $value): string
    {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            '/<object\b[^>]*>.*?<\/object>/is',
            '/<embed\b[^>]*>/i',
            '/<svg\b[^>]*on\w+\s*=/i',
            '/\bon\w+\s*=\s*"[^"]*"/i',
            '/\bon\w+\s*=\s*\'[^\']*\'/i',
            '/\bon\w+\s*=\s*[^\s>]+/i',
            '/javascript:/i',
        ];

        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }

        return $value;
    }
}
