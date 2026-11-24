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
        if (!$this->matchField($rule->ip_isp, (string) ($context['isp'] ?? ''))) {
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
