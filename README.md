# Renegade Service Provider

Project provides services that can be integrated into console commands for project builds. Some vendors (iRep etc.) require assets to be packaged in very specific ways. Also provides methods for automating screenshot pdf generation of projects for client review.

## Project Setup

Add the following lines to composer.json

```
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "renegade/renegadeserviceprovider",
                "version": "1.0.0",
                "source": {
                    "url": "https://github.com/tlherr/RenegadeServiceProvider.git",
                    "type": "git",
                    "reference": "master"
                },
                "autoload": {
                    "classmap": ["VendorServices/src"]
                }
            }
        }
    ],
```

and then add 

```
    "require": {
              "renegade/renegadeserviceprovider": "1.0.0"
    }

```

in silex application add

```
$app->register(new \Renegade\VendorServices\RenegadeServiceProvider());
```

then use methods as you see fit

example:
```
$app['renegade_irep']->build($input, $output);
```

## License

GPL v2
