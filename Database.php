<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    private $beforeSpot = "";
    private $afterSpot = "";

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $buildedQuery = $query;
        $this->beforeSpot = "";
        $this->afterSpot = "";

        $argIndex = 0;
        $argsCount = count($args);

        $this->beforeSpot = "";
        $this->afterSpot = $buildedQuery;

        $spotIndex = mb_strpos($this->afterSpot, "?");
        $nextArg = $args[$argIndex];

        $modifiers = ["d", "f", "a", "#"];

        while($spotIndex !== false && $argIndex++ < $argsCount) {
            $this->beforeSpot = $this->beforeSpot . substr($this->afterSpot, 0, $spotIndex);
            $modifier = $this->afterSpot[$spotIndex + 1];
            $haveModifier = in_array($modifier, $modifiers, true);
            $modifier = $haveModifier ? $modifier : "";
            $this->afterSpot = substr($this->afterSpot, $spotIndex + ($haveModifier? 2 : 1));

            if (!$haveModifier) {
                $this->beforeSpot .= $this->escapeArgument($nextArg);
            }

            if ($haveModifier) {
                switch ($modifier) {
                    case '#':
                        if (is_array($nextArg)) {
                            $this->beforeSpot .= implode(
                                ", ",
                                array_map(
                                    [$this, 'escapeColumn'],
                                    $nextArg
                                )
                            );
                        } else {
                            $this->beforeSpot .= $this->escapeColumn($nextArg);
                        }
                        break;

                    case 'f':
                    case 'd':
                        $this->beforeSpot .= $this->escapeArgument($nextArg, $modifier);
                        break;

                    case 'a':
                        if (is_array($nextArg)) {
                            $parts = [];
                            // assoc array vs just arrays
                            if (array_keys($nextArg) === range(0, count($nextArg) - 1)) {
                                foreach ($nextArg as $key => $value) {
                                    $parts[] = $this->escapeArgument($value);
                                }
                            } else {
                                foreach ($nextArg as $key => $value) {
                                    $part = $this->escapeColumn($key);
                                    $part .= " = ";
                                    $part .= $this->escapeArgument($value, $modifier);
                                    $parts[] = $part;
                                }
                            }
                            $this->beforeSpot .= implode(", ", $parts);
                        } else {
                            throw new Exception("Error in argument '{$nextArg}' type, expected Array", 1);
                        }
                        break;

                    default: // never here
                        $this->beforeSpot .= $this->escapeArgument($nextArg, $modifier);
                        break;
                }
            }



            $spotIndex = mb_strpos($this->afterSpot, "?");
            $nextArg = $args[$argIndex];
        }

        $buildedQuery = $this->beforeSpot . $this->afterSpot;

        if ($spotIndex !== false && $argIndex != $argsCount) {
            throw new Exception("No argument to place in spot", 1);
        }

        if ($spotIndex === false && $argIndex < $argsCount) {
            throw new Exception("No spot to place an argument", 1);
        }

        return $buildedQuery;
    }

    private function escapeArgument($arg, $modifier = "") {
        // bad style with side effects, but this is just a test task
        if ($arg === $this->skip()) {
            $lastOpenBracketIndex = mb_strrpos($this->beforeSpot, "{");
            $firstCloseBracketIndex = mb_strpos($this->afterSpot, "}");
            if ($lastOpenBracketIndex === false || $firstCloseBracketIndex === false) {
                throw new Exception("No matching brackets to skip", 1);
            }

            $this->beforeSpot = mb_substr($this->beforeSpot, 0, $lastOpenBracketIndex);
            $this->afterSpot = mb_substr($this->afterSpot, $firstCloseBracketIndex + 1);
            return "";
        } else {
            // in case we are in block, but there were no skip
            $lastOpenBracketIndex = mb_strrpos($this->beforeSpot, "{");
            $firstCloseBracketIndex = mb_strpos($this->afterSpot, "}");
            if ($lastOpenBracketIndex !== false && $firstCloseBracketIndex !== false) {
                $this->beforeSpot =
                    mb_substr($this->beforeSpot, 0, $lastOpenBracketIndex)
                    . mb_substr($this->beforeSpot, $lastOpenBracketIndex + 1);
                $this->afterSpot =
                    mb_substr($this->afterSpot, 0, $firstCloseBracketIndex)
                    . mb_substr($this->afterSpot, $firstCloseBracketIndex + 1);
            }
        }

        if (is_null($arg)) {
            return "NULL";
        }

        if ($modifier === "d") {
            if ($arg != (int) $arg) {
                throw new Exception("Error in argument '{$arg}' type, expected Int", 1);
            }
            $arg = (int) $arg;
            if (!is_int($arg)) {
                throw new Exception("Error in argument '{$arg}' type, expected Int", 1);
            }

            return $arg;
        }

        if ($modifier === "f") {
            if ($arg != (float) $arg) {
                throw new Exception("Error in argument '{$arg}' type, expected Float", 1);
            }
            $arg = (float) $arg;
            if (!is_float($arg)) {
                throw new Exception("Error in argument '{$arg}' type, expected Float", 1);
            }

            return $arg;
        }

        if (is_bool($arg)) {
            $result = $arg? 1 : 0;
        } elseif (is_numeric($arg)) {
            $result = (string) $arg;
        } elseif (is_string($arg)) {
            $result = "'" . $this->mysqli->real_escape_string($arg) . "'";
        } else {
            throw new Exception("Error in argument type, type: '" . gettype($arg) . "' is not supported", 1);
        }

        return $result;
    }

    private function escapeColumn($arg) {
        return "`". $this->mysqli->real_escape_string((string) $arg)."`";
    }

    public function skip()
    {
        return "------###------"; // why not?).
    }
}
