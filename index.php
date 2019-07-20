<?php

require('vendor/autoload.php');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Alow-Headers, X-Requested-With');

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

$db = new PDO('sqlite:' . __DIR__ . '/db.sqlite');

$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::int(),
        'name' => Type::string(),
        'admin' => Type::boolean(),
    ],
]);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'users' => [
            'type' => Type::listOf($userType),
            'resolve' => function ($root, $args) {
                global $db;
                
                $results = [];

                foreach ($db->query('SELECT * FROM users') as $row) {
                    $results[] = $row;
                }

                return $results;
            }
        ],
        'user' => [
            'type' => $userType,
            'args' => [
                'id' =>  Type::nonNull(Type::int()),
            ],
            'resolve' => function ($root, $args) {
                global $db;

                $query = $db->query('SELECT * FROM users WHERE id=:id');
                $query->bindParam(':id', $args['id']);
                $query->execute();

                return $query->fetch(PDO::FETCH_ASSOC);
            }
        ],
    ],
]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'addUser' => [
            'type' => $userType,
            'args' => [
                'name' => Type::string(),
                'admin' => Type::boolean(),
            ],
            'resolve' => function ($root, $args) {
                global $db;

                $query = $db->prepare('INSERT INTO users (name, admin) VALUES (:name, :admin)');
                $query->bindParam(':name', $args['name']);
                $query->bindParam(':admin', $args['admin']);
                $query->execute();

                return [
                    'id' => $db->lastInsertId(),
                    'name' => $args['name'],
                    'admin' =>  $args['admin'],
                ];
            }
        ],
        'deleteUser' => [
            'type' => $userType,
            'args' => [
                'id' =>  Type::nonNull(Type::int()),
            ],
            'resolve' => function ($root, $args) {
                global $db;

                $query = $db->query('DELETE FROM users WHERE id=:id');
                $query->bindParam(':id', $args['id']);
                $query->execute();

                return [
                    'id' => null,
                ];
            }
        ]
    ]
]);

$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
]);

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$query = $input['query'];
$variableValues = isset($input['variables']) ? $input['variables'] : null;

try {
    $result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);
    $output = $result->toArray();
} catch (\Exception $e) {
    $output = [
        'errors' => [
            [
                'message' => $e->getMessage()
            ]
        ]
    ];
}
header('Content-Type: application/json');
echo json_encode($output);
