Code d'Azur Social
=============

Provides basic twitter and facebook connect to use with Laravel 4.
 
Instalation
===========

Add codedazur-social to your composer.json file.

    require : {
        "laravel/framework": "4.1.*",
        "codedazur/social": "dev-master"
    }

Or with composer command:

    composer require "codedazur/social": "dev-master"

Add provider to your app/config/app.php providers

    'Codedazur\Social\SocialServiceProvider',

Publish config

    php artisan config:publish codedazur/social
    
Publish assets

    php artisan asset:publish codedazur/social
    
Publish migration

    php artisan migrate --package="codedazur/social"