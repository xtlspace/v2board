<?php

namespace App\Services;

use App\Models\SubRule;
use Illuminate\Support\Facades\Cache;

class SubRuleMatcher
{
    public function rules()
    {
        return Cache::remember('sub_rules', 60, function () {
            return SubRule::where('enable', 1)->orderBy('sort')->orderBy('id')->get();
        });
    }

    public function apply(array $servers, array $context): array
    {
        $rules = $this->rules();
        if ($rules->isEmpty()) {
            return $servers;
        }

        foreach ($servers as $key => $server) {
            $ruleContext = $context;
            $ruleContext['host'] = (string) ($server['host'] ?? '');
            $ruleContext['port'] = $server['port'] ?? null;

            foreach ($rules as $rule) {
                if (!$this->matches($rule, $ruleContext)) {
                    continue;
                }
                if ((int) $rule->type === 1) {
                    break;
                }
                $servers[$key]['host'] = $rule->replace_host;
                if ($rule->replace_port) {
                    $servers[$key]['port'] = $rule->replace_port;
                }
                break;
            }
        }

        return $servers;
    }

    public function test(array $context): array
    {
        $original = [
            'host' => (string) ($context['host'] ?? ''),
            'port' => $context['port'] ?? null,
        ];

        foreach ($this->rules() as $rule) {
            if (!$this->matches($rule, $context)) {
                continue;
            }
            if ((int) $rule->type === 1) {
                return [
                    'matched' => true,
                    'rule' => $rule,
                    'original' => $original,
                    'result' => $original,
                    'skipped' => true,
                ];
            }
            return [
                'matched' => true,
                'rule' => $rule,
                'original' => $original,
                'result' => [
                    'host' => $rule->replace_host ?: $original['host'],
                    'port' => $rule->replace_port ?: $original['port'],
                ],
                'skipped' => false,
            ];
        }

        return [
            'matched' => false,
            'original' => $original,
        ];
    }

    public function matches(SubRule $rule, array $context): bool
    {
        if (!$this->matchField($rule->user_agent, (string) ($context['user_agent'] ?? ''), false)) {
            return false;
        }
        if (!$this->matchField($rule->ip_country, (string) ($context['country'] ?? ''))) {
            return false;
        }
        if (!$this->matchField($rule->ip_region, (string) ($context['region'] ?? ''))) {
            return false;
        }
        if (!$this->matchField($rule->ip_city, (string) ($context['city'] ?? ''))) {
            return false;
        }
        if (!$this->matchIspExpression((string) ($rule->ip_isp ?? ''), (string) ($context['isp'] ?? ''))) {
            return false;
        }
        if (!$this->matchField($rule->user_id, (string) ($context['user_id'] ?? ''))) {
            return false;
        }
        if ($rule->original_host && $rule->original_host !== (string) ($context['host'] ?? '')) {
            return false;
        }
        return true;
    }

    public function validateIspExpression(string $expression): bool
    {
        $expression = trim($expression);
        if ($expression === '') {
            return true;
        }
        try {
            $this->parseIspAst($expression);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    public function matchIspExpression(string $expression, string $actual): bool
    {
        $expression = trim($expression);
        if ($expression === '') {
            return true;
        }
        try {
            $ast = $this->parseIspAst($expression);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
        return $this->evaluateIspAst($ast, $actual);
    }

    private function parseIspAst(string $expression): array
    {
        $tokens = $this->tokenizeIsp($expression);
        if (empty($tokens)) {
            throw new \InvalidArgumentException('empty expression');
        }
        $index = 0;
        $ast = $this->parseIspOr($tokens, $index);
        if ($index !== count($tokens)) {
            throw new \InvalidArgumentException('unexpected token');
        }
        return $ast;
    }

    private function tokenizeIsp(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $index = 0;
        while ($index < $length) {
            $char = $expression[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }
            if ($char === '(' || $char === ')') {
                $tokens[] = $char;
                $index++;
                continue;
            }
            $start = $index;
            while ($index < $length) {
                $current = $expression[$index];
                if (ctype_space($current) || $current === '(' || $current === ')') {
                    break;
                }
                $index++;
            }
            $token = substr($expression, $start, $index - $start);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    private function parseIspOr(array $tokens, int &$index): array
    {
        $items = [$this->parseIspAnd($tokens, $index)];
        while ($index < count($tokens) && strtoupper($tokens[$index]) === 'OR') {
            $index++;
            $items[] = $this->parseIspAnd($tokens, $index);
        }
        return count($items) === 1 ? $items[0] : ['type' => 'or', 'items' => $items];
    }

    private function parseIspAnd(array $tokens, int &$index): array
    {
        $items = [$this->parseIspUnary($tokens, $index)];
        while ($index < count($tokens) && strtoupper($tokens[$index]) === 'AND') {
            $index++;
            $items[] = $this->parseIspUnary($tokens, $index);
        }
        return count($items) === 1 ? $items[0] : ['type' => 'and', 'items' => $items];
    }

    private function parseIspUnary(array $tokens, int &$index): array
    {
        if ($index >= count($tokens)) {
            throw new \InvalidArgumentException('expected value');
        }

        $token = $tokens[$index];
        if ($token === '(') {
            $index++;
            $node = $this->parseIspOr($tokens, $index);
            if ($index >= count($tokens) || $tokens[$index] !== ')') {
                throw new \InvalidArgumentException('missing closing parenthesis');
            }
            $index++;
            return $node;
        }
        if ($token === ')' || strtoupper($token) === 'AND' || strtoupper($token) === 'OR') {
            throw new \InvalidArgumentException('unexpected operator');
        }
        if (strcasecmp($token, 'NOT:') === 0) {
            $index++;
            return ['type' => 'not', 'item' => $this->parseIspUnary($tokens, $index)];
        }
        if (strcasecmp($token, 'FULL:') === 0) {
            $index++;
            $node = $this->parseIspUnary($tokens, $index);
            if (($node['type'] ?? '') !== 'term') {
                throw new \InvalidArgumentException('FULL: requires a value');
            }
            $node['full'] = true;
            return $node;
        }

        $index++;
        return $this->parseIspTermToken($token);
    }

    private function parseIspTermToken(string $token): array
    {
        $not = false;
        $full = false;
        while (true) {
            if (strncasecmp($token, 'NOT:', 4) === 0) {
                $not = !$not;
                $token = substr($token, 4);
                continue;
            }
            if (strncasecmp($token, 'FULL:', 5) === 0) {
                $full = true;
                $token = substr($token, 5);
                continue;
            }
            break;
        }
        if ($token === '' || strtoupper($token) === 'AND' || strtoupper($token) === 'OR') {
            throw new \InvalidArgumentException('invalid value');
        }

        $node = ['type' => 'term', 'value' => $token, 'full' => $full];
        return $not ? ['type' => 'not', 'item' => $node] : $node;
    }

    private function evaluateIspAst(array $node, string $actual): bool
    {
        switch ($node['type'] ?? '') {
            case 'or':
                foreach ($node['items'] as $item) {
                    if ($this->evaluateIspAst($item, $actual)) {
                        return true;
                    }
                }
                return false;
            case 'and':
                foreach ($node['items'] as $item) {
                    if (!$this->evaluateIspAst($item, $actual)) {
                        return false;
                    }
                }
                return true;
            case 'not':
                return !$this->evaluateIspAst($node['item'], $actual);
            case 'term':
                $value = (string) $node['value'];
                if (!empty($node['full'])) {
                    return $actual === $value;
                }
                if (function_exists('mb_stripos')) {
                    return mb_stripos($actual, $value) !== false;
                }
                return stripos($actual, $value) !== false;
            default:
                return false;
        }
    }

    private function matchField($ruleValue, string $actualValue, bool $exact = true): bool
    {
        if (empty($ruleValue)) {
            return true;
        }

        $not = str_starts_with((string) $ruleValue, 'NOT:');
        if ($not) {
            $ruleValue = substr((string) $ruleValue, 4);
        }
        $full = str_starts_with((string) $ruleValue, 'FULL:');
        if ($full) {
            $ruleValue = substr((string) $ruleValue, 5);
        }

        $values = explode(',', (string) $ruleValue);
        foreach ($values as $val) {
            $val = trim($val);
            if ($val === '') {
                continue;
            }
            if (preg_match('/^(>=|<=|>|<)(\d+)$/', $val, $m)) {
                $num = (int) $m[2];
                $actualNum = (int) $actualValue;
                $matched = false;
                switch ($m[1]) {
                    case '>':
                        $matched = $actualNum > $num;
                        break;
                    case '<':
                        $matched = $actualNum < $num;
                        break;
                    case '>=':
                        $matched = $actualNum >= $num;
                        break;
                    case '<=':
                        $matched = $actualNum <= $num;
                        break;
                }
                if ($matched) {
                    return !$not;
                }
            } elseif ($exact || $full) {
                if ($actualValue === $val) {
                    return !$not;
                }
            } else {
                if (strpos(strtolower($actualValue), strtolower($val)) !== false) {
                    return !$not;
                }
            }
        }

        return $not;
    }
}
