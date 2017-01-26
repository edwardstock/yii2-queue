<?php

namespace edwardstock\queue\helpers;

/**
 * log_request. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class ArrayHelper extends \yii\helpers\ArrayHelper
{
    /**
     * @param array $arr1
     * @param array $arr2
     *
     * @return bool
     */
    public static function arraysAreEquals(array $arr1, array $arr2)
    {
        return count(array_diff_assoc($arr2, $arr1)) === 0;
    }

    /**
     * @param array $array1
     * @param array $array2
     *
     * @return int
     */
    public static function arrayDiffAssocRecursive(array $array1, array $array2)
    {
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key])) {
                    $difference[$key] = $value;
                } elseif (!is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = self::arrayDiffAssocRecursive($value, $array2[$key]);
                    if ($new_diff != false) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (!isset($array2[$key]) || $array2[$key] != $value) {
                $difference[$key] = $value;
            }
        }

        return !isset($difference) ? 0 : $difference;
    }

    /**
     * Merges two or more arrays into one recursively.
     * If each array has an element with the same string key value, the latter
     * will overwrite the former (different from array_merge_recursive).
     * Recursive merging will be conducted if both arrays have an element of array
     * type and are having the same key.
     * For integer-keyed elements, the elements from the latter array will
     * be appended to the former array.
     *
     * @param array $a array to be merged to
     * @param array $b array to be merged from. You can specify additional
     *                 arrays via third argument, fourth argument etc.
     *
     * @return array the merged array (the original arrays are not changed.)
     */
    public static function merge($a, $b)
    {
        $args = func_get_args();
        $res  = array_shift($args);
        while (!empty($args)) {
            $next = array_shift($args);
            foreach ($next as $k => $v) {
                if (is_int($k)) {
                    if (isset($res[$k])) {
                        //если смердживаемые элементы снова являются массивами, то их опять надо мерджить а не просто рядом добавлять
                        if (is_array($res[$k])) {
                            $res[$k] = self::merge($res[$k], $v);
                        } else {
                            $res[] = $v;
                        }
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = self::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }

    /**
     * @param array $arr
     *
     * @return int
     */
    public static function hashSum(array $arr): int
    {
        $recursive = false;
        foreach ($arr AS $k => $v) {
            if (is_array($v)) {
                $recursive = true;
                break;
            }
        }

        if (!$recursive) {
            return crc32(implode(':', array_keys($arr)) . implode(':', $arr));
        }

        $hashCollection = [];
        $func           = function (array $new) use (&$func, &$hashCollection) {
            $rec     = false;
            $recVals = [];
            foreach ($new AS $k => $v) {
                if (is_array($v)) {
                    $rec       = true;
                    $recVals[] = $v;
                }
            }

            if (!$rec) {
                $hashCollection[] = crc32(implode(':', array_keys($new)) . implode(':', $new));
            } else {
                foreach ($recVals AS $val) {
                    $hashCollection[] = $func($val);
                }
            }
        };

        $func($arr);

        return crc32(implode(':', array_keys($hashCollection)) . implode(':', $hashCollection));
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public static function getRandomValue(array $data)
    {
        if (empty($data)) {
            return null;
        }

        return $data[random_int(0, sizeof($data) - 1)];
    }

    /**
     * @param array    $data
     * @param array    $to
     * @param callable $callback
     */
    public static function pushAllCallback(array $data, array &$to, callable $callback)
    {
        foreach ($data AS $key => $value) {
            $res = call_user_func($callback, $key, $value);
            if (is_array($res)) {
                $to[$res[0]] = $res[1];
            } else {
                $to[] = $res;
            }
        }
    }

    /**
     * @param array $data
     * @param array $to
     * @param bool  $preserveKeys
     * @param bool  $skipExisted
     */
    public static function pushAll(array $data, array &$to, bool $preserveKeys = false, bool $skipExisted = true)
    {
        foreach ($data AS $i => $value) {
            if ($preserveKeys) {
                if ($skipExisted && array_key_exists($to, $i)) {
                    continue;
                } else {
                    $to[$i] = $value;
                }
            } else {
                $to[] = $value;
            }
        }
    }

    /**
     * @param array $data
     * @param array $to
     *
     * @return void
     */
    public static function pushAllUnique(array $data, array &$to)
    {
        if (sizeof($to) === 0) {
            $to = $data;

            return;
        } else {
            if (sizeof($data) === 0) {
                return;
            }
        }

        $uniqueValues = [];
        $target       = [];
        foreach ($to AS $item) {
            if (sizeof($item) == 0) {
                continue;
            }
            $hash = crc32(json_encode($item));
            if (isset($uniqueValues[$hash])) {
                continue;
            }

            $uniqueValues[$hash] = 1;
            $target[]            = $item;
        }
        unset($hash, $item);

        foreach ($data AS $item) {
            if (sizeof($item) == 0) {
                continue;
            }
            $hash = crc32(json_encode($item));
            if (isset($uniqueValues[$hash])) {
                continue;
            }

            $uniqueValues[$hash] = 1;
            $target[]            = $item;
        }

        $to = $target;
    }
}