PHP module for Nette to create REST API
=======================================

Info
----
PHP module for Nette to create REST API

Features
--------
- takes http request and processes it according to your rules from config
- checks existence of called presenter, method and params
- validates annotated params by type

Using
-----
- make your api presenters extend NetteRestApi\BaseApiPresenter
- create config routes.neon (by provided example) and load it in bootstrap
- register your NetteRestApi\ApiRouteProcessor service (as in provided example)
- enjoy the api

Installing
----------

put this code into your composer.json file:

        {
        "repositories": [
                {
                    "type": "vcs",
                    "url": "https://github.com/proclarush-teonas/nette-rest-api.git"
                }
            ],
            "require": {
                "php": ">=7.0.0",
                "NetteRestApi": "dev-master"
            }
        }

then in console run this command:

        composer install

