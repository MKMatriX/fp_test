<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $buildedQuery = $query;

        $argIndex = 0;
        $argsCount = count($args);

        $spotIndex = mb_strpos($buildedQuery, "?");
        $nextArg = $args[$argIndex];

        $modifiers = ["d", "f", "a", "#"];

        while($spotIndex !== false && $argIndex++ < $argsCount) {
            $beforeSpot = substr($buildedQuery, 0, $spotIndex);
            $modifier = $buildedQuery[$spotIndex + 1];
            $haveModifier = in_array($modifier, $modifiers, true);
            $afterSpot = substr($buildedQuery, $spotIndex + ($haveModifier? 2 : 1));

            $buildedQuery = $beforeSpot;

            if (!$haveModifier) {
                $buildedQuery .= $this->escapeArgument($nextArg);
            }

            if ($haveModifier) {
                switch ($modifier) {
                    case '#':
                        if (is_array($nextArg)) {
                            $buildedQuery .= implode(
                                ", ",
                                array_map(
                                    [$this, 'escapeColumn'],
                                    $nextArg
                                )
                            );
                        } else {
                            $buildedQuery .= $this->escapeColumn($nextArg);
                        }
                        break;

                    case 'd':
                        $buildedQuery .= (int) $nextArg;
                        break;

                    case 'f':
                        break;

                    case 'a':
                        if (is_array($nextArg)) {
                            $parts = [];
                            foreach ($nextArg as $key => $value) {
                                $part = $this->escapeColumn($key);
                                $part .= " = ";
                                $part .= $this->escapeArgument($value);
                                $parts[] = $part;
                            }
                            $buildedQuery .= implode(", ", $parts);
                        }
                        break;

                    default: // never here
                        $buildedQuery .= $this->escapeArgument($nextArg);
                        break;
                }
            }

            $buildedQuery .= $afterSpot;


            $spotIndex = mb_strpos($buildedQuery, "?");
            $nextArg = $args[$argIndex];
        }

        return $buildedQuery;
    }

    private function escapeArgument($arg) {
        $result = "";

        if (is_null($arg)) {
            $result = "NULL";
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
        // throw new Exception();
    }
}
