# Categories multilevel with single model file for Laravel 5


## Tips and tricks

- [Seeding](#seeding)


### Seeding
Simple data

```php
$categories = [
    ['name' => 'TV & Home Theather'],
    ['name' => 'Tablets & E-Readers'],
    ['name' => 'Computers', 'children' => [
        [
            'name' => 'Laptops', 'children' => [
            ['name' => 'PC Laptops'],
            ['name' => 'Macbooks (Air/Pro)']
        ]
        ],
        ['name' => 'Desktops', 'children' => [
            // These will be created
            ['name' => 'Towers Only'],
            ['name' => 'Desktop Packages'],
            ['name' => 'All-in-One Computers'],
            ['name' => 'Gaming Desktops']
        ]],
        ['name' => 'Monitors'],
    ]],
    ['name' => 'Cell Phones']
];
\App\Models\Category::buildTree($categories);
```

> Updating