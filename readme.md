# Media Functionality for Backpack CRUD fields

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![The Whole Fruit Manifesto](https://img.shields.io/badge/writing%20standard-the%20whole%20fruit-brightgreen)](https://github.com/the-whole-fruit/manifesto)

If you project uses both [Spatie Media Library](https://github.com/spatie/laravel-medialibrary) and [Backpack for Laravel](https://backpackforlaravel.com/), this package adds the ability for Backpack fields to easily store uploaded files as media (by using Spatie Media Library). More exactly, it provides some helper classes that will handle the file upload and retrieval. You'll love how simple it makes it to do uploads.

## Requirements

**Install and use `spatie/laravel-medialibrary` v10**. If you haven't already, please make sure you've installed `spatie/laravel-medialibrary` and followed all installation steps in [their docs](https://spatie.be/docs/laravel-medialibrary/v10/installation-setup):

``` bash
# require the package
composer require backpack/media-library-uploads

# prepare the database
# NOTE: Spatie migration does not come with a `down()` method by default, add one now if you need it
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="migrations"

# run the migration
php artisan migrate

# make you have your storage symbolic links created
php artisan storage:link

# (optionally) publish the config file
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="config"

```

Then prepare your Models to use `spatie/laravel-medialibrary`, by adding the `InteractsWithMedia` trait to your model and implement the `HasMedia` interface like explained on [Media Library Documentation](https://spatie.be/docs/laravel-medialibrary/v10/basic-usage/preparing-your-model).

## Installation

Just require this package using Composer, that's it:

``` bash
composer require backpack/media-library-uploads
```

## Usage

On any field where you upload a file (eg. `upload`, `upload_multiple`, `image`, `base64_image`), add `withMedia()` to your field definition, in order to tell Backpack to store those uploaded files using Spatie's Laravel MediaLibrary. For example:

```php
CRUD::field('avatar')->type('image')->withMedia();
```

For repeatable fields you should add `->withMedia()` to the repeatable field (the parent), but also in each subfields that should be handled by Media Library, you should mark that field with `'withMedia' => true`.

```php
CRUD::field('gallery')
        ->label('Image Gallery')
        ->type('repeatable')
        ->subfields([
            [
                'name' => 'main_image',
                'label' => 'Main Image',
                'type' => 'image',
                'withMedia' => true,
            ],
        ])
        ->withMedia(); 
```

## Advanced Use

### Overriding the defaults

Backpack sets up some handy defaults for you when handling the media. But it also provides you a way to customize the bits you need from Spatie Media Library. You can pass a configuration array to `->withMedia([])` or `'withMedia' => []` to override the defaults Backpack has set:

```php
CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia([
            'collection' => 'my_collection', // default: the spatie config default
            'disk' => 'my_disk', // default: the spatie config default
            'mediaName' => 'custom_media_name' // default: the field name
        ]);
```

### Customizing the saving process (adding thumbnails, etc)

Inside the same configuration array mentioned above, you can use the `whenSaving` closure to customize the saving process. This closure will be called in THE MIDDLE of configuring the media collection. So AFTER calling the initializer function, but BEFORE calling toMediaCollection(). Do what you want to the $spatieMedia object, using Spatie's documented methods, then `return` it back to Backpack to call the termination method. Sounds good?

```php
CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia([
            'whenSaving' => function($spatieMedia, $backpackMediaObject) {
                return $spatieMedia->usingFileName('main_image.jpg')
                                    ->withResponsiveImages();
            }
        ]);
```

**NOTE:** Some methods will be called automatically by Backpack; You shoudn't call them inside the closure used for configuration: `toMediaCollection()`, `setName()`, `usingName()`, `setOrder()`, `toMediaCollectionFromRemote()` and `toMediaLibrary()`. They will throw an error if you manually try to call them in the closure. 

### Defining media collection in the model

You can also have the collection configured in your model as explained in [Spatie Documentation](https://spatie.be/docs/laravel-medialibrary/v10/working-with-media-collections/defining-media-collections), in that case, you just need to pass the `collection` configuration key. But you are still able to configure all the other options including the `whenSaving` callback.

```php
// In your Model.php

public function registerMediaCollections(): void
{
    $this
        ->addMediaCollection('product_images')
        ->useDisk('products');
}

CRUD::field('main_image')
        ->label('Main Image')
        ->type('image')
        ->withMedia([
            'collection' => 'product_images', // will pick the collection definition from your model
        ]);
```


## Change log

Changes are documented here on Github. Please see the [Releases tab](https://github.com/backpack/media-library-connector/releases).

## Testing

``` bash
composer test
```

## Contributing

Please see [contributing.md](contributing.md) for a todolist and howtos.

## Security

If you discover any security related issues, please email hello@backpackforlaravel.com instead of using the issue tracker.

## Credits

- [Pedro Martins](https://github.com/pxpm/) - author & architect
- [Cristian Tabacitu](https://github.com/tabacitu) - reviewer
- [Backpack for Laravel](https://github.com/laravel-backpack) - sponsor
- [All Contributors][link-contributors]

## License

This project was released under MIT, so you can install it on top of any Backpack & Laravel project. Please see the [license file](license.md) for more information. 


[ico-version]: https://img.shields.io/packagist/v/backpack/media-library-connector.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/backpack/media-library-connector.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/backpack/media-library-connector
[link-downloads]: https://packagist.org/packages/backpack/media-library-connector
[link-author]: https://github.com/backpack
[link-contributors]: ../../contributors
