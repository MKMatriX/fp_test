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
                $buildedQuery .= "'" . $this->mysqli->real_escape_string($nextArg) . "'";
            }

            if ($haveModifier) {
                switch ($modifier) {
                    case '#':
                        if (is_array($nextArg)) {
                            $buildedQuery .= implode(
                                ", ",
                                array_map(
                                    fn ($a) => "`". $this->mysqli->real_escape_string((string) $a)."`",
                                    $nextArg
                                )
                            );
                        } else {
                            $buildedQuery .= "`". $this->mysqli->real_escape_string((string) $nextArg)."`";
                        }
                        break;

                    case 'd':
                        $buildedQuery .= (int) $nextArg;
                        break;

                    case 'f':
                        break;

                    case 'a':
                        break;

                    default: // never here
                        $buildedQuery .= "'" . $this->mysqli->real_escape_string((string) $nextArg) . "'";
                        break;
                }
            }

            $buildedQuery .= $afterSpot;


            $spotIndex = mb_strpos($buildedQuery, "?");
            $nextArg = $args[$argIndex];
        }

        return $buildedQuery;
    }

    public function skip()
    {
        // throw new Exception();
    }
}
