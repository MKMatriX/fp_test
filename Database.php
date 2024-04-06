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

        $spotIndex = mb_strpos($buildedQuery, "?");
        $nextArg = $args[$argIndex];

        $modifiers = ["d", "f", "a", "#"];

        while($spotIndex !== false && $argIndex++ < $argsCount) {
            $this->beforeSpot = substr($buildedQuery, 0, $spotIndex);
            $modifier = $buildedQuery[$spotIndex + 1];
            $haveModifier = in_array($modifier, $modifiers, true);
            $modifier = $haveModifier ? $modifier : "";
            $this->afterSpot = substr($buildedQuery, $spotIndex + ($haveModifier? 2 : 1));

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
                                    if (is_numeric($value)) {
                                        $parts[] = $value;
                                    } else {
                                        $this->escapeArgument($value, $modifier);
                                    }
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
                        }
                        break;

                    default: // never here
                        $this->beforeSpot .= $this->escapeArgument($nextArg, $modifier);
                        break;
                }
            }

            $buildedQuery = $this->beforeSpot . $this->afterSpot;


            $spotIndex = mb_strpos($buildedQuery, "?");
            $nextArg = $args[$argIndex];
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
        }

        if (is_null($arg)) {
            return "NULL";
        }

        if ($modifier === "d") {
            $arg = (int) $arg;
            if (!is_int($arg)) {
                throw new Exception("Error in argument '#{$arg}' type, expected Int", 1);
            }

            return $arg;
        }

        if ($modifier === "d") {
            $arg = (float) $arg;
            if (!is_float($arg)) {
                throw new Exception("Error in argument '#{$arg}' type, expected Float", 1);
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
            $result = "'" . $this->mysqli->real_escape_string((string) $arg) . "'";
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
