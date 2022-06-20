<?php
declare(strict_types=1);

namespace DevTool\LaravelDevTool\Tools;

use Illuminate\Support\Str;

trait ResolveGenerator
{

    /**
     * @param array $rules
     * @param array $parametersAttributes
     * @param array $parametersDescribe
     * @param array $parametersDefaultValue
     * @return array
     */
    protected function resolveGetRules(array $rules, array $parametersAttributes, array $parametersDescribe, array $parametersDefaultValue): array
    {
        foreach ($rules as $key => $rule) {
            $rules[$key] = $this->explodeExplicitRule($rule);

            if (Str::contains($key, '*') || Str::contains($key, '.')) {
                $newKey = str_replace('*', '0', $key);
                $newKeyArr = explode('.', $newKey);

                if (1 == count($newKeyArr)) {
                    $rules[$newKeyArr[0]]['schema']['type'] = 'array';
                    $rules[$newKeyArr[0]]['schema']['items']['type'] = $this->analysisRuleType($rules[$key]);
                }
                unset($rules[$key]);
            } else {
                if (in_array('required', $rules[$key])) {
                    $rules[$key]['required'] = true;
                } else {
                    $rules[$key]['required'] = false;
                }
                !isset($rules[$key]['schema']['type']) && $rules[$key]['schema']['type'] = $this->analysisRuleType($rules[$key]);
                $rules[$key]['schema']['default'] = $parametersDefaultValue[$key] ?? '';
                $rules[$key]['title'] = ($parametersDescribe[$key] ?? '') ?: ($parametersAttributes[$key] ?? '');
                $rules[$key]['description'] = $parametersAttributes[$key] ?? '';
            }
        }

        return $rules;
    }

    /**
     * @param array $rules
     * @param array $parametersAttributes
     * @param array $parametersDescribe
     * @param array $parametersDefaultValue
     * @return array
     */
    protected function resolveBodyRules(array $rules, array $parametersAttributes, array $parametersDescribe, array $parametersDefaultValue): array
    {
        $implicitRules = [];
        $properties = [];
        $required = [];

        foreach ($rules as $key => $rule) {
            $rules[$key] = $rule = $this->explodeExplicitRule($rule);

            if (Str::contains($key, '*') || Str::contains($key, '.')) {
                $newKey = str_replace('*', '_items', $key);
                $newKeyArr = explode('.', $newKey);
                $currentItem = &$implicitRules;
                $i = 0;
                $this->resolveItem($newKeyArr, $i, [
                    'type' => $this->analysisRuleType($rule),
                    'title' => $parametersAttributes[$key] ?? '',
                    'default' => $parametersDefaultValue[$key] ?? '',
                    'description' => ($parametersDescribe[$key] ?? '') ?: ($parametersAttributes[$key] ?? ''),
                    'required' => in_array('required', $rule),
                ], $currentItem, $implicitRules);
                unset($rules[$key]);
            } else {
                array_push($required, $key);
                $properties[$key]['type'] = $this->analysisRuleType($rules[$key]);
                $properties[$key]['title'] = ($parametersDescribe[$key] ?? '') ?: ($parametersAttributes[$key] ?? '');
                $properties[$key]['default'] = $parametersDefaultValue[$key] ?? '';
                $properties[$key]['description'] = $parametersAttributes[$key] ?? '';
            }
        }

        foreach ($properties as $key => $property) {
            if (empty($properties[$key]['items'])) {
                $this->buildMsg($key . 'no set items');
                $properties[$key]['items'] = [
                    'type' => 'string',
                ];
            }

            if (!isset($implicitRules[$key])) {
                continue;
            }
            $properties[$key] = array_merge($property, $implicitRules[$key]);
            if ('array' != $properties[$key]['type']) {
                continue;
            }


            $this->bindProperties($properties[$key]);
            $this->formattingProperties($properties[$key]);
        }

        return [
            $properties,
            $required,
        ];
    }

    /**
     * @param $array
     * @param $i
     * @param $implicitRules
     * @param $currentItem
     * @param array $content
     */
    public function resolveItem($array, $i, array $content, &$implicitRules, &$currentItem)
    {
        $value = $array[$i];

        if (0 == $i) {
            $implicitRules[$value] = $implicitRules[$value] ?? [];
            unset($currentItem);
            $currentItem =& $implicitRules[$value]['_properties'];
            $i++;

            return $this->resolveItem($array, $i, $content, $implicitRules, $currentItem);
        }

        if (!isset($array[$i + 1])) {
            $currentItem[$value] = $content;

            return;
        }
        $i++;

        if ('_items' == $value && !isset($array[$i])) {
            $currentItem['_items'] = $currentItem['_items'] ?? [];

            return $this->resolveItem($array, $i, $content, $implicitRules, $currentItem['_items']);
        }
        $currentItem[$value]['_properties'] = $currentItem[$value]['_properties'] ?? [];

        return $this->resolveItem($array, $i, $content, $implicitRules, $currentItem[$value]['_properties']);
    }

    /**
     * @param $property
     */
    public function bindProperties(&$property)
    {
        if (isset($property['_properties']) && !isset($property['_properties']['_items'])) {
            $property['type'] = 'object';
            $property['required'] = [];

            foreach ($property['_properties'] as $key => $item) {
                true == $item['required'] && $property['required'][] = $key;
                unset($property['_properties'][$key]['required']);
                $this->bindProperties($property['_properties'][$key]);
            }
        }

        if (isset($property['_properties'], $property['_properties']['_items']['_properties'])) {
            $property['_items'] = $property['_properties']['_items'];
            unset($property['_properties']);
            $property['_items']['type'] = 'object';
            $property['_items']['required'] = [];

            foreach ($property['_items']['_properties'] as $key => &$item) {
                true == $item['required'] && $property['_items']['required'][] = is_array($key) ? key($key) : $key;
                unset($property['_items']['_properties'][$key]['required']);
                $this->bindProperties($property['_items']['_properties'][$key]);
            }
        } elseif (isset($property['_properties'], $property['_properties']['_items'])) {
            $property['required'] = [];
            $item = $property['_properties']['_items'];
            unset($property['_properties']);
            $property['_items'] = $item;

            if (isset($property['_items']['_properties'])) {
                $property['_items']['type'] = 'object';
                $this->bindProperties($property['_items']);
            }
        }
    }

    /**
     * @param $property
     * @return mixed
     */
    public function formattingProperties(&$property)
    {
        if (isset($property['_properties'])) {
            $property['properties'] = $property['_properties'];
            unset($property['_properties']);
            foreach ($property['properties'] as $key => $item) {
                $this->formattingProperties($property['properties'][$key]);
            }
        }

        if (isset($property['_items'])) {
            $property['items'] = $property['_items'];
            unset($property['_items']);
            if (isset($property['items']['_properties'])) {
                $property['items']['properties'] = $property['items']['_properties'];
                unset($property['items']['_properties']);
                foreach ($property['items']['properties'] as $key => $item) {
                    $this->formattingProperties($property['items']['properties'][$key]);
                }
            } else {
                $this->formattingProperties($property['items']);
            }
        }

        return;
    }

    /**
     * @param $orgProperties
     * @param $genProperties
     */
    public function recursiveProperties($orgProperties, &$genProperties)
    {
        foreach ($orgProperties as $key => $item) {
            $genProperties[$key] = [
                'default' => $item['default'] ?? '',
                'description' => $item['description'] ?? '',
            ];
            $schema =& $genProperties[$key];

            if (isset($item['is_array']) && $item['is_array']) {
                $schema['type'] = 'array';
                $schema['items'] = [
                    'type' => $item['type'],
                ];
                $schema['items']['properties'] = [];
                $properties = &$schema['items']['properties'];
            } else {
                $schema['type'] = $item['type'];
                $schema['properties'] = [];
                $properties = &$schema['properties'];
            }

            if (!empty($item['_properties'])) {
                $this->recursiveProperties($item['_properties'], $properties);
            }

            if (!empty($item['required'])) {
                $genProperties['required'][] = $key;
            }
        }
    }

    /**
     * Explode the explicit rule into an array if necessary.
     *
     * @param mixed $rule
     * @return array
     */
    protected function explodeExplicitRule($rule): array
    {
        if (is_string($rule)) {
            return explode('|', $rule);
        }

        if (is_array($rule)) {
            return $rule;
        }

        return [(string)$rule];
    }

    /**
     * @param array $params
     * @return string
     */
    public function analysisRuleType(array $params): string
    {
        if (array_intersect(['array'], $params)) {
            return 'array';
        }

        if (array_intersect(['integer', 'numeric', 'int'], $params)) {
            return 'integer';
        }

        if (array_intersect(['bool', 'boolean'], $params)) {
            return 'boolean';
        }

        return 'string';
    }

}