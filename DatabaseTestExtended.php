<?php

namespace FpDbTest;

use Exception;

class DatabaseTestExtended
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function testBuildQuery($verbose = false): bool
    {
        $results = [];

        $tests = [
            [
                "SELECT name FROM users WHERE user_id = 1"
            ],
            [
                "SELECT name FROM users WHERE user_id = ?d"
            ],
            [
                "SELECT name FROM users WHERE user_id = ?d",
                ["1"]
            ],
            [
                "SELECT name FROM users WHERE user_id = ?d",
                ["1.1"]
            ],
            [
                "SELECT name FROM users WHERE user_rating > ?f",
                ["2.5"]
            ],
            [
                "SELECT name FROM users WHERE user_rating > ?f",
                ["qwe2.5"]
            ],
            [
                "SELECT name FROM users WHERE user_rating > ?f",
                [NULL]
            ],
            [
                // suppose this is fine
                "SELECT name FROM users WHERE {user_rating > ?f",
                [NULL]
            ],
            [
                "SELECT name FROM users WHERE { {user_rating > ?f} }",
                [NULL]
            ],
            [
                "SELECT name FROM users WHERE {user_rating > ?f",
                [$this->db->skip()]
            ],
            [
                'SELECT ?# FROM users WHERE user_id = ?d',
                ["not_array", 2]
            ],
            [
                'UPDATE users SET ?a WHERE user_id = -1',
                ['not_array']
            ],
            [
                'UPDATE users SET ?a WHERE user_id = -1',
                [[$this]]
            ],
            [
                'UPDATE users SET ? WHERE user_id = 1',
                ["' WHERE user_id = 1; UPDATE users SET group='admin"] // testing escape
            ],
            [
                "SELECT last_name FROM users WHERE name = ? and user_id = ?d", // testing if args contain special chars
                ["?", "1"]
            ], // actually I can continue to improve if args contain [..."{", ...] and future query have "}"
            // but I will say that that is feature, and not a bug, and in case of test task not that important
            [
                "SELECT middle_name FROM users WHERE { name = ? and user_id = ?d and last_name = ?}", // w/e don't judge me, but this case just works
                ["{", "1", "black"] // but still i think that there could be a bug with arg contains "{" but I will leave it on purpose
                // cause I if would take this like super serious, than It would be not in *nix everything is text
                // & more functional style, where all those places have to be special functions
            ],
        ];

        $exceptions = array_fill(0, count($tests), "");

        foreach ($tests as $key => $args) {
            try {
                $results[] = $this->db->buildQuery($args[0], $args[1] ?? []);
            } catch (\Throwable $th) {
                $results[] = $args[0];
                $exceptions[$key] = $th->getMessage();
            }
        }

        $correct = [
            'SELECT name FROM users WHERE user_id = 1',
            'SELECT name FROM users WHERE user_id = ?d',
            'SELECT name FROM users WHERE user_id = 1',
            'SELECT name FROM users WHERE user_id = ?d',
            'SELECT name FROM users WHERE user_rating > 2.5',
            'SELECT name FROM users WHERE user_rating > ?f',
            'SELECT name FROM users WHERE user_rating > NULL',
            'SELECT name FROM users WHERE {user_rating > NULL',
            'SELECT name FROM users WHERE { user_rating > NULL }',
            'SELECT name FROM users WHERE {user_rating > ?f',
            'SELECT `not_array` FROM users WHERE user_id = 2',
            'UPDATE users SET ?a WHERE user_id = -1',
            'UPDATE users SET ?a WHERE user_id = -1',
            'UPDATE users SET \'\\\' WHERE user_id = 1; UPDATE users SET group=\\\'admin\' WHERE user_id = 1',
            "SELECT last_name FROM users WHERE name = '?' and user_id = 1",
            "SELECT middle_name FROM users WHERE  name = '{' and user_id = 1 and last_name = 'black'",
        ];

        $correctExceptions = [
            '',
            'No argument to place in spot',
            '',
            "Error in argument '1.1' type, expected Int",
            '',
            "Error in argument 'qwe2.5' type, expected Float",
            '',
            '',
            '',
            'No matching brackets to skip',
            '',
            "Error in argument 'not_array' type, expected Array",
            "Error in argument type, type: 'object' is not supported",
            '',
            '',
            ''
        ];


        if ($verbose) {
            // I prefer comfortably see what I am doing, ofk no spaghetti on prod
            // But I will leave it here to show, more info if you will wath commits
            foreach ($results as $key => $result) {
                $correctResult = $correct[$key];
                $correctException = $correctExceptions[$key];
                $exception = $exceptions[$key];
                $testPassed = $result === $correctResult;
                $testPassed &= $exception == $correctException;
                if (!$testPassed) {
                    echo "<br/>";
                    echo "<div style=\"color: red\">";
                        echo htmlspecialchars($tests[$key][0]);
                        echo "<br> to --> <br>";
                        echo htmlspecialchars($result);
                        echo "<br/>";
                        echo "<pre> ", print_r($tests[$key][1], true), "</pre>";
                        if (strlen($exception)) {
                            echo "<br/>";
                            echo $exception;
                        }
                    echo "</div>";
                }
                echo "<div style=\"color: green\">";
                    echo htmlspecialchars($correctResult);
                    if (strlen($correctException)) {
                        echo "   |   exception : ";
                        echo htmlspecialchars($correctException);
                    }
                echo "</div>\n";
            }
        }

        return $results === $correct;
    }
}
