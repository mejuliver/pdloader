copy app/Helpers/pdloader.php add to your Laravel app "app/Helpers"

autoload helper, add to composer.json

"autoload": {
        "classmap": [
            "database"
        ],
        "psr-4": {
            "App\\": "app/"
        },
        "files": [
               "app/Helpers/pdloader.php"
         ]
    },

then do "composer dump-autoload"

now you can use globally to your Laravel app view, controllers...

e.g.
pdloader()->load('basicapp')->header(); 