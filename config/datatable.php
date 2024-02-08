<?php

return [
    'resources' => [
        'grid' => DataTable\Resources\Grid::class,
        'api' => DataTable\Resources\Api::class,
        'collection' => DataTable\Resources\Collection::class
    ],
    'collection' => DataTable\GeneralCollection::class,
    'strict_with_mode' => true // determines if the requested relations have to be in "with" parameter or not
];
